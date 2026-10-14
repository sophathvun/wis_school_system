import Swal from 'sweetalert2';
import { isIosDevice } from './installShortcut';

export function initPhoneNotificationOnboarding(enableNotifications) {
    const userId = document.body?.dataset.currentUserId;
    const mobile = isIosDevice() || /Android/i.test(navigator.userAgent);
    if (!userId || !mobile) return;

    const standalone = window.matchMedia('(display-mode: standalone)');
    const storageKey = `wis:notification-onboarding:${userId}`;
    const remindAfter = 7 * 24 * 60 * 60 * 1000;
    let installed = standalone.matches || window.navigator.standalone === true;
    let offered = false;
    let timer;

    const offer = async () => {
        if (offered || !installed || document.visibilityState === 'hidden') return;
        if (!document.body?.dataset.webPushPublicKey || !('serviceWorker' in navigator)
            || !('PushManager' in window) || !('Notification' in window) || Notification.permission !== 'default') return;
        try {
            const lastOffered = Number(window.localStorage.getItem(storageKey));
            if (lastOffered > 0 && Date.now() - lastOffered < remindAfter) return;
        } catch {
            // Continue when browser storage is unavailable.
        }
        if (Swal.isVisible()) {
            timer = window.setTimeout(offer, 500);
            return;
        }
        offered = true;
        try { window.localStorage.setItem(storageKey, String(Date.now())); } catch {}
        let enableAttempt;
        const result = await Swal.fire({
            icon: 'info',
            title: 'Enable notifications?',
            text: 'Receive new messages and important school updates on this phone. Tap “Yes, enable!” and then choose “Allow” when your phone asks.',
            confirmButtonText: 'Yes, enable!', cancelButtonText: 'Later', showCancelButton: true,
            didOpen: () => {
                // Hide our guide before the phone opens its permission prompt, while
                // retaining the actual confirmation tap required by iOS.
                Swal.getConfirmButton().addEventListener('click', () => {
                    Swal.getContainer().style.display = 'none';
                    enableAttempt = enableNotifications();
                    enableAttempt.catch(() => {});
                }, { capture: true });
            },
        });
        if (result.isConfirmed && enableAttempt) {
            try {
                await enableAttempt;
                window.dispatchEvent(new Event('school:notifications-enabled'));
                await Swal.fire({
                    icon: 'success', title: 'Notifications enabled!',
                    text: 'New messages and important updates can now reach this phone.',
                    toast: true, position: 'top-end', showConfirmButton: false,
                    timer: 4500, timerProgressBar: true,
                });
            } catch (error) {
                await Swal.fire({ icon: 'info', title: 'Enable phone notifications', text: error.message || 'Unable to enable notifications. Please try again.' });
            }
        }
    };
    const schedule = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(offer, 500);
    };
    window.addEventListener('appinstalled', () => { installed = true; schedule(); });
    standalone.addEventListener?.('change', event => {
        installed = event.matches || window.navigator.standalone === true;
        schedule();
    });
    document.addEventListener('visibilitychange', schedule);
    schedule();
}
