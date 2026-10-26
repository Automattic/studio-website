"""Prepares the video's inputs: site images, sites.json, timeline.json and the soundtrack mix.

Run with the scratch venv's Python (numpy, scipy, Pillow):  python prep.py
"""
import json
import os

import numpy as np
from PIL import Image
from scipy.io import wavfile
from scipy.signal import butter, sosfilt

ROOT = os.path.dirname(os.path.abspath(__file__))
PUB = os.path.join(ROOT, 'public')
CAP = os.path.expanduser('~/Studio/showcase-captures')
FPS, BAR = 30, 60

# --- Sites -------------------------------------------------------------------
showcase = json.load(open(os.path.join(CAP, 'showcase.json')))
os.makedirs(os.path.join(PUB, 'sites'), exist_ok=True)
sites = []
for s in showcase:
    slug = os.path.basename(s['images']['hero'])[: -len('-hero.jpg')]
    out = os.path.join(PUB, 'sites', f'{slug}.jpg')
    if not os.path.exists(out) or os.path.getmtime(out) < os.path.getmtime(s['images']['hero']):
        Image.open(s['images']['hero']).convert('RGB').resize((1440, 900), Image.LANCZOS).save(out, quality=90)
    sites.append(dict(slug=slug, name=s['name'], kind=s['kind'], model=s['model']))
json.dump(sites, open(os.path.join(ROOT, 'src', 'sites.json'), 'w'), indent=1, ensure_ascii=False)
have = {s['slug'] for s in sites}


def pick(wanted, fallbacks, n=4):
    chosen = [w for w in wanted if w in have]
    chosen += [f for f in fallbacks if f in have and f not in chosen]
    return chosen[:n]


groups = [
    ['Any business.', pick(['kestrel', 'little-molars-pediatric-dentistry', 'brightside-immigration-law', 'petal-stem'], ['saltmarsh-coffee-roasters', 'township-of-greenfield'])],
    ['Any style.', pick(['ironside-strength', 'vela', 'hack-the-harbor', 'pixel-tide-games'], ['northlight-architects', 'quill-margin'])],
    ['Any language.', pick(['atelier-noor', 'dar-zitoun', 'stadtwerk-coworking', 'hanami-yoga'], ['taller-barro-negro', 'volt-garage'])],
]
assert all(len(g[1]) == 4 for g in groups), groups
agent_site = 'petal-stem' if 'petal-stem' in have else 'hollow-pine-records'

# --- Timeline (frames; every scene starts on a bar) --------------------------
order = [('intro', 2), ('typing', 3), ('agent', 3), ('reveal', 4), ('montage', 6), ('wall', 2), ('editor', 3), ('end', 5)]
scenes, t = [], 0
for sid, bars in order:
    scenes.append(dict(id=sid, from_=t, frames=bars * BAR))
    t += bars * BAR
timeline = dict(
    totalFrames=t,
    scenes=[dict(id=s['id'], **{'from': s['from_'], 'frames': s['frames']}) for s in scenes],
    groups=groups,
    agentSite=agent_site,
    agentPrompt='Build a site for my florist in Hackney with WordPress Studio.' if agent_site == 'petal-stem' else 'Build a site for my record label with WordPress Studio.',
)
json.dump(timeline, open(os.path.join(ROOT, 'src', 'timeline.json'), 'w'), indent=1)
start = {s['id']: s['from_'] / FPS for s in scenes}
TOTAL = t / FPS

# --- Soundtrack --------------------------------------------------------------
SR = 44100
N = int((TOTAL + 0.5) * SR)
rng = np.random.default_rng(7)


def load(name):
    sr, a = wavfile.read(os.path.join(PUB, 'audio', f'{name}.wav'))
    assert sr == SR
    a = a.astype(np.float32) / 32768.0
    return a if a.ndim == 2 else np.stack([a, a], 1)


def track(name, spans, gain, fade=0.03):
    """Loop `name` on the global bar grid, audible within each (start, end) span in seconds."""
    loop = load(name)
    out = np.zeros((N, 2), np.float32)
    reps = int(np.ceil(N / len(loop)))
    tiled = np.tile(loop, (reps, 1))[:N]
    env = np.zeros(N, np.float32)
    for a, b, *opt in spans:
        fin = opt[0] if opt else fade
        fout = opt[1] if len(opt) > 1 else fade
        i, j = int(a * SR), min(N, int(b * SR))
        e = np.ones(j - i, np.float32)
        fi, fo = int(fin * SR), int(fout * SR)
        if fi:
            e[:fi] *= np.linspace(0, 1, fi)
        if fo:
            e[-fo:] *= np.linspace(1, 0, fo)
        env[i:j] = np.maximum(env[i:j], e)
    return tiled * env[:, None] * gain


def one_shot(name, at, gain):
    a = load(name)
    out = np.zeros((N, 2), np.float32)
    i = int(at * SR)
    j = min(N, i + len(a))
    out[i:j] = a[: j - i] * gain
    return out


mix = np.zeros((N, 2), np.float32)
E = TOTAL
mix += track('moving-pictures-synth', [(0, E - 1.5, 2.5, 4.0)], 0.30)
mix += track('moving-pictures-acoustic-guitar', [(4, 40, 0.4), (42, E - 2, 0.5, 4.0)], 0.30)
mix += track('moving-pictures-bass', [(8, 40, 0.2), (42, E - 4, 0.3, 3.0)], 0.36)
mix += track('minimal-backbeat-02', [(8, 16), (42, 46)], 0.30)
mix += track('doubledown-beat-01', [(16, 40)], 0.30)
mix += track('moving-pictures-rhythm-guitar', [(16, 40), (46, 52, 0.03, 0.4)], 0.22)
mix += track('disco-swagger-beat-01', [(46, 52, 0.03, 0.25)], 0.30)
mix += one_shot('80s-synth-fx-riser-01', 12.0, 0.30)
mix += one_shot('80s-synth-fx-riser-01', 42.0, 0.26)


def noise(sec):
    return rng.standard_normal(int(sec * SR)).astype(np.float32)


def place(sig, at, gain, pan=0.0):
    i = int(at * SR)
    j = min(N, i + len(sig))
    if i >= N or j <= i:
        return
    l, r = np.sqrt(0.5 * (1 - pan)), np.sqrt(0.5 * (1 + pan))
    mix[i:j, 0] += sig[: j - i] * gain * l
    mix[i:j, 1] += sig[: j - i] * gain * r


def bandpass(x, lo, hi, order=2):
    return sosfilt(butter(order, [lo, hi], btype='band', fs=SR, output='sos'), x)


def whoosh(length=0.55):
    """Band-limited noise swelling into the cut (ends exactly at the cut)."""
    x = noise(length)
    n = len(x)
    out = np.zeros(n, np.float32)
    # Sweep the band upward in short blocks.
    blocks = 24
    for b in range(blocks):
        a, z = b * n // blocks, (b + 1) * n // blocks
        f = 300 + (b / blocks) ** 1.6 * 5000
        out[a:z] = bandpass(x[a:z], f, f * 2.2)
    env = np.linspace(0, 1, n) ** 2.2
    env[-int(0.04 * SR):] *= np.linspace(1, 0, int(0.04 * SR))
    return out * env


def click():
    x = bandpass(noise(0.018), 2500, 7000)
    return x * np.exp(-np.linspace(0, 9, len(x)))


def blip(freq=1760, dur=0.09):
    tt = np.linspace(0, dur, int(dur * SR), False)
    return (np.sin(2 * np.pi * freq * tt) + 0.3 * np.sin(4 * np.pi * freq * tt)) * np.exp(-tt * 38)


def pop():
    tt = np.linspace(0, 0.06, int(0.06 * SR), False)
    f = 900 * np.exp(-tt * 30) + 300
    return np.sin(2 * np.pi * np.cumsum(f) / SR) * np.exp(-tt * 60)


# Scene-change whooshes, landing on the downbeat.
for sid in ['typing', 'reveal', 'montage', 'wall', 'editor', 'end']:
    w = whoosh()
    place(w, start[sid] - len(w) / SR, 0.07, pan=rng.uniform(-0.3, 0.3))
# Mosaic tiles settling in the intro and the end card.
for k in range(14):
    place(pop(), 0.15 + k * 0.125 + rng.uniform(0, 0.05), 0.035, pan=rng.uniform(-0.6, 0.6))
for k in range(10):
    place(pop(), start['end'] + 0.1 + k * 0.14 + rng.uniform(0, 0.05), 0.03, pan=rng.uniform(-0.6, 0.6))
# Typing: the typing scene maps frames 16–150 onto the 95 captured frames.
ts = start['typing']
for k in range(150):
    tk = ts + (16 + k * (134 / 150)) / FPS
    if rng.random() < 0.72:
        place(click(), tk + rng.uniform(-0.01, 0.01), 0.10 * rng.uniform(0.6, 1.0), pan=rng.uniform(-0.2, 0.2))
# Agent checklist: a soft blip as each step completes (Agent.tsx: 20 + i*13, done ~ +12).
for i in range(8):
    place(blip(1568 if i % 2 else 1760), start['agent'] + (20 + i * 13 + 12) / FPS, 0.05)
# Montage: a light swish per shot.
for k in range(12):
    w = whoosh(0.22)
    place(w, start['montage'] + k - len(w) / SR + 1 / FPS, 0.035, pan=0.4)

# --- Master: gentle high-pass, gain to target, soft limiter, fade -------------
mix = sosfilt(butter(2, 30, btype='high', fs=SR, output='sos'), mix, axis=0).astype(np.float32)
rms = np.sqrt(np.mean(mix[int(16 * SR):int(40 * SR)] ** 2))
mix *= (10 ** (-15.5 / 20)) / max(rms, 1e-6)
ceiling = 10 ** (-1.0 / 20)
mix = np.tanh(mix / ceiling) * ceiling
# The published cut starts at TRIM_S (render with --frames from that frame): silence before it and fade in.
TRIM_S = 6.0
mix[: int(TRIM_S * SR)] = 0
ramp_n = int(0.25 * SR)
mix[int(TRIM_S * SR): int(TRIM_S * SR) + ramp_n] *= np.linspace(0, 1, ramp_n)[:, None]
tail = int(1.2 * SR)
mix[-tail:] *= np.linspace(1, 0, tail)[:, None]
wavfile.write(os.path.join(PUB, 'audio', 'soundtrack.wav'), SR, (mix * 32767).astype(np.int16))
print('sites', len(sites), '| groups', [g[1] for g in groups], '| agent', agent_site)
print('duration %.1fs, peak %.2f, rms(16-40s) %.1f dBFS' % (len(mix) / SR, np.abs(mix).max(), 20 * np.log10(np.sqrt(np.mean(mix[int(16 * SR):int(40 * SR)] ** 2)))))
