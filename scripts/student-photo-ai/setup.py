"""One-time, project-local CPU model setup. No student images leave the server."""
import hashlib
import os
from pathlib import Path
import subprocess
import sys
import urllib.request
import venv

ROOT = Path(__file__).resolve().parents[2]
TARGET = ROOT / 'storage/app/private/student-photo-ai'
SHA256 = '07c308cf0fc7e6e8b2065a12ed7fc07e1de8febb7dc7839d7b7f15dd66584df9'
REVISION = 'fa2fa546052fba4c08921230a26cc69a333fca12'


def main():
    if sys.version_info < (3, 12):
        raise SystemExit('Photo AI setup requires Python 3.12 or newer. Run setup with a supported Python executable.')
    print(f'Preparing local photo runtime with Python {sys.version.split()[0]}', flush=True)
    TARGET.mkdir(parents=True, exist_ok=True)
    python = TARGET / 'venv' / ('Scripts/python.exe' if os.name == 'nt' else 'bin/python')
    if not python.exists():
        venv.create(TARGET / 'venv', with_pip=True)
    subprocess.run([str(python), '-m', 'pip', 'install', '--disable-pip-version-check',
                    '--only-binary=:all:', '-r', str(Path(__file__).with_name('requirements.txt'))], check=True)
    model = TARGET / 'modnet.onnx'
    if not model.exists() or hashlib.sha256(model.read_bytes()).hexdigest() != SHA256:
        temporary = TARGET / 'modnet.download'
        try:
            urllib.request.urlretrieve(f'https://huggingface.co/Xenova/modnet/resolve/{REVISION}/onnx/model.onnx', temporary)
            if hashlib.sha256(temporary.read_bytes()).hexdigest() != SHA256:
                raise RuntimeError('Model checksum mismatch. The download was not installed.')
            temporary.replace(model)
        finally:
            temporary.unlink(missing_ok=True)
    # Compile/cache foreground estimation during setup, rather than making the
    # first user wait for Numba's one-time compilation inside a web request.
    environment = dict(os.environ, NUMBA_NUM_THREADS='2',
                       NUMBA_CACHE_DIR=str(TARGET / 'numba-cache'))
    subprocess.run([str(python), '-c',
                    'import numpy as np; from pymatting import estimate_foreground_ml, estimate_alpha_cf; '
                    'estimate_foreground_ml(np.ones((8,8,3), dtype=np.float32), '
                    'np.full((8,8), 0.5, dtype=np.float32)); '
                    'a=np.tile(np.linspace(0,1,8),(8,1)); '
                    'estimate_alpha_cf(np.repeat(a[:,:,None],3,axis=2), a)'], env=environment, check=True)
    print('Local student photo model and edge cleanup ready:', python)


if __name__ == '__main__':
    main()
