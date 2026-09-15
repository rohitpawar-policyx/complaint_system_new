/**
 * Complaint chat: Laravel Echo over Reverb (WebSockets), plus a plain
 * `fetch` POST for sending (no polling anywhere in this file).
 *
 * Reverb connection details (key/host/port/scheme) come from data-*
 * attributes rendered by Blade from config('reverb...'), not from Vite's
 * import.meta.env - see the note in .env.example for why: those values
 * must reflect the container's real *runtime* env (which differs between
 * local/UAT/production), but Vite bakes import.meta.env in at `docker
 * build` time, before Render has injected the real values.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function escapeHtml(value) {
    const div = document.createElement('div');
    div.textContent = value;
    return div.innerHTML;
}

function formatTimestamp(iso) {
    return new Date(iso).toLocaleString();
}

document.querySelectorAll('[data-chat]').forEach((root) => {
    const complaintId = root.dataset.complaintId;
    const sendUrl = root.dataset.sendUrl;
    const csrfToken = root.dataset.csrfToken;
    const currentUserId = Number(root.dataset.currentUserId);
    const messagesEl = root.querySelector('[data-chat-messages]');
    const form = root.querySelector('[data-chat-form]');
    const textarea = form ? form.querySelector('textarea') : null;
    const errorEl = root.querySelector('[data-chat-error]');

    function showError(text) {
        if (!errorEl) return;
        errorEl.textContent = text;
        errorEl.hidden = false;
    }

    function appendMessage(msg) {
        if (!messagesEl) return;

        const mine = Number(msg.sender_id) === currentUserId;
        const el = document.createElement('div');
        el.className = `chat-message ${mine ? 'chat-message--mine' : 'chat-message--theirs'}`;
        el.dataset.messageId = msg.id;
        el.innerHTML = `
            <div class="chat-message-meta">
                <strong>${escapeHtml(msg.sender_name)}</strong>
                <time>${escapeHtml(formatTimestamp(msg.created_at))}</time>
            </div>
            <p class="chat-message-text">${escapeHtml(msg.message)}</p>
        `;
        messagesEl.appendChild(el);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    // Land scrolled to the latest message already rendered by Blade on load.
    if (messagesEl) {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    if (!window.Echo) {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: root.dataset.reverbKey,
            wsHost: root.dataset.reverbHost,
            wsPort: Number(root.dataset.reverbPort) || 80,
            wssPort: Number(root.dataset.reverbPort) || 443,
            forceTLS: root.dataset.reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    // Subscribing to a private channel round-trips through POST
    // /broadcasting/auth first (registered by BroadcastServiceProvider);
    // routes/channels.php's `chat.{complaintId}` callback runs there and
    // rejects anyone who isn't this complaint's owner or an admin - that
    // server-side check, not anything in this file, is what actually stops
    // a user from listening in on someone else's complaint chat.
    window.Echo.private(`chat.${complaintId}`)
        .listen('.message.sent', (event) => {
            appendMessage(event);
        })
        .error(() => {
            showError('Live connection lost — reload the page to see new messages.');
        });

    if (form && textarea) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const message = textarea.value.trim();
            if (!message) return;

            const button = form.querySelector('button[type="submit"]');
            if (button) button.disabled = true;
            if (errorEl) errorEl.hidden = true;

            fetch(sendUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message }),
            })
                .then((response) => {
                    if (!response.ok) {
                        return response.json().then((data) => {
                            throw new Error(data.message || 'The message could not be sent.');
                        });
                    }
                    // No optimistic render here - this message (like every
                    // other) is rendered exclusively by the Echo listener
                    // above, so there is exactly one rendering code path.
                    textarea.value = '';
                })
                .catch((error) => {
                    showError(error.message);
                })
                .finally(() => {
                    if (button) button.disabled = false;
                });
        });
    }
});
