export function isIosDevice(browserNavigator = window.navigator) {
    return /iPhone|iPad|iPod/i.test(browserNavigator.userAgent) ||
        (browserNavigator.platform === 'MacIntel' && browserNavigator.maxTouchPoints > 1);
}

export function setupInstallShortcut() {
    const button = document.getElementById('installAppShortcut');
    const isIos = isIosDevice();
    const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    const mobile = isIos || /Android/i.test(window.navigator.userAgent) || window.matchMedia('(max-width: 767.98px)').matches;
    if (!button || standalone || !mobile) return;

    let deferredInstallPrompt = null;
    const showButton = () => button.classList.remove('d-none');
    const hideButton = () => button.classList.add('d-none');
    if (isIos) {
        button.classList.add('is-ios');
        const label = button.querySelector('span');
        if (label) label.textContent = 'How to Add Shortcut';
        showButton();
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredInstallPrompt = event;
        showButton();
    });
    window.addEventListener('appinstalled', () => {
        deferredInstallPrompt = null;
        hideButton();
    });

    button.addEventListener('click', async () => {
        if (deferredInstallPrompt) {
            deferredInstallPrompt.prompt();
            await deferredInstallPrompt.userChoice.catch(() => null);
            deferredInstallPrompt = null;
            hideButton();
            return;
        }

        if (isIos) {
            const device = /iPad/i.test(window.navigator.userAgent) || window.navigator.platform === 'MacIntel' ? 'iPad' : 'iPhone';
            const message = '1. Tap the Share button in your browser (square with an upward arrow). It may be under the menu (…).\n2. Scroll down and choose Add to Home Screen.\n3. Tap Add to place WIS School on your Home Screen.\nIf this option is missing, open the website in Safari and try again.';
            if (window.Swal) {
                window.Swal.fire({
                    title: `Add to ${device} Home Screen`,
                    html: `<div class="ios-shortcut-instructions">
                        <ol>
                            <li>Tap your browser’s <strong>Share</strong> button
                                <svg class="ios-shortcut-share-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-label="Share: square with an upward arrow" role="img"><path d="M12 16V2m-4 4 4-4 4 4M8 9H4v12h16V9h-4"/></svg>.
                                <small>You may find it under the menu (…).</small>
                            </li>
                            <li>Scroll down and choose <strong>Add to Home Screen</strong>.</li>
                            <li>Tap <strong>Add</strong> to place <strong>WIS School</strong> on your Home Screen.</li>
                        </ol>
                        <p>If this option is missing, open this website in <strong>Safari</strong> and try again.</p>
                    </div>`,
                    confirmButtonText: 'Close instructions',
                    customClass: { popup: 'ios-shortcut-dialog' },
                });
            } else {
                window.alert(message);
            }
            return;
        }

        const message = 'Open this system in Chrome, tap the browser menu, then choose Add to Home screen.';
        if (window.Swal) {
            window.Swal.fire({ icon: 'info', title: 'Add shortcut to phone', text: message, confirmButtonText: 'OK' });
        } else {
            window.alert(message);
        }
    });
}
