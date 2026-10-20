"""Offline deployment check. Uses no student photos and makes no network calls."""
import argparse
import hashlib
import importlib
import importlib.metadata
import json
from pathlib import Path
import sys
import tempfile

SHA256 = '07c308cf0fc7e6e8b2065a12ed7fc07e1de8febb7dc7839d7b7f15dd66584df9'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('model', type=Path)
    parser.add_argument('directory', type=Path)
    args = parser.parse_args()
    checks = []

    def check(name, action):
        try:
            checks.append({'name': name, 'ok': True, 'detail': str(action())})
        except Exception as error:
            checks.append({'name': name, 'ok': False, 'detail': str(error)[:500]})

    checks.append({'name': 'Python runtime', 'ok': True, 'detail': sys.version.split()[0]})
    for module, package in [('numpy', 'numpy'), ('PIL', 'Pillow'), ('onnxruntime', 'onnxruntime'),
                            ('pymatting', 'pymatting'), ('scipy', 'scipy'), ('numba', 'numba')]:
        def dependency(module=module, package=package):
            importlib.import_module(module)
            return importlib.metadata.version(package)
        check(package, dependency)
    for module in ['foreground', 'refine', 'fabric']:
        check(f'Photo script: {module}', lambda module=module: importlib.import_module(module).__file__)

    def model():
        if hashlib.sha256(args.model.read_bytes()).hexdigest() != SHA256:
            raise RuntimeError('Model checksum mismatch. Re-run scripts/student-photo-ai/setup.py on this server.')
        import onnxruntime as ort
        options = ort.SessionOptions()
        options.intra_op_num_threads = 2
        options.inter_op_num_threads = 1
        ort.InferenceSession(str(args.model), sess_options=options, providers=['CPUExecutionProvider'])
        return 'Checksum verified; ONNX CPU model loads successfully'
    check('Portrait model', model)

    def private_storage():
        with tempfile.TemporaryDirectory(prefix='check-', dir=args.directory) as directory:
            probe = Path(directory) / 'probe'
            probe.write_bytes(b'photo-runtime-check')
            if probe.read_bytes() != b'photo-runtime-check':
                raise RuntimeError('Private storage write/read failed.')
        return 'Temporary files can be created, read, and removed'
    check('Private storage', private_storage)
    ok = all(entry['ok'] for entry in checks)
    print(json.dumps({'ok': ok, 'checks': checks}))
    return 0 if ok else 1


if __name__ == '__main__':
    sys.exit(main())
