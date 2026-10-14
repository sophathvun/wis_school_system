import Swal from 'sweetalert2';
import { isIosDevice } from './installShortcut';

export function setupWebPush() {
    const buttons = [...document.querySelectorAll('[data-enable-phone-notifications]')];
    let registrationPromise;
    let subscribing;
    const registration = () => {
        registrationPromise ||= navigator.serviceWorker.register('/sw.js')
            .then(() => navigator.serviceWorker.ready)
            .catch(error => { registrationPromise = null; throw error; });
        return registrationPromise;
    };
    const supported = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    const subscribe = async () => {
        const publicKey = document.body?.dataset.webPushPublicKey || '';
        if (!supported()) throw new Error(isIosDevice()
            ? 'On iPhone or iPad, use Share → Add to Home Screen, then open WIS School from that icon. iOS 16.4 or later is required.'
            : 'Open the installed WIS School app in a browser that supports phone notifications, such as Chrome.');
        if (!publicKey) throw new Error('Phone notifications are not configured on the server yet. Please contact your administrator.');
        // Request permission before awaiting registration to retain the user's tap on iOS.
        if (Notification.permission === 'default') await Notification.requestPermission();
        if (Notification.permission !== 'granted') throw new Error('Notifications are blocked. Allow notifications for WIS School in your phone or browser settings, then try again.');
        const worker = await registration();
        let subscription = await worker.pushManager.getSubscription();
        if (!subscription) {
            const padded = publicKey + '='.repeat((4 - publicKey.length % 4) % 4);
            const decoded = window.atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
            subscription = await worker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: Uint8Array.from(decoded, character => character.charCodeAt(0)),
            });
        }
        const response = await fetch('/push/subscriptions', {
            method: 'POST', credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json', 'Content-Type': 'application/json',
            },
            body: JSON.stringify(subscription.toJSON()),
        });
        if (!response.ok || response.redirected) throw new Error('Unable to save phone notifications. Refresh this page, sign in again if needed, and retry.');
    };
    window.schoolEnsurePushSubscription = () => {
        if (subscribing) return subscribing;
        subscribing = subscribe().finally(() => { subscribing = null; });
        return subscribing;
    };
    buttons.forEach(button => button.addEventListener('click', async () => {
        if (button.disabled) return;
        button.disabled = true;
        try {
            await window.schoolEnsurePushSubscription();
            window.dispatchEvent(new Event('school:notifications-enabled'));
            await Swal.fire({ icon: 'success', title: 'Phone notifications enabled', text: 'New notifications and messages can now reach this device. On iPhone, allow Badges in Settings → Notifications → WIS School. Android controls whether the icon shows a dot or a number.' });
        } catch (error) {
            await Swal.fire({ icon: 'info', title: 'Enable phone notifications', text: error.message });
        } finally {
            button.disabled = false;
        }
    }));
    if ('serviceWorker' in navigator) {
        registration().then(() => {
            if (supported() && Notification.permission === 'granted' && document.body?.dataset.webPushPublicKey) {
                return window.schoolEnsurePushSubscription();
            }
        }).catch(() => {});
    }
}
