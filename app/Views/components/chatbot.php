<?php
helper('url');
$csrfTokenName = csrf_token();
$csrfTokenHash = csrf_hash();
?>

<div class="slams-chatbot" id="slamsChatbot">
    <button class="chatbot-toggle" id="chatbotToggle" type="button" aria-label="Open chatbot" aria-expanded="false">
        <i class="bi bi-chat-dots"></i>
    </button>

    <div class="chatbot-panel" id="chatbotPanel" aria-hidden="true">
        <div class="chatbot-header">
            <div class="chatbot-title">
                <i class="bi bi-cpu"></i>
                <div>
                    <h6>Smart Lab Assistant</h6>
                    <p class="chatbot-subtitle">Quick insight commands</p>
                </div>
            </div>
            <button class="btn btn-sm chatbot-close" type="button" id="chatbotClose" aria-label="Close chatbot">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="chatbot-commands" id="chatbotCommands">
            <button type="button" class="chatbot-command" data-command="Total bookings">Total bookings</button>
            <button type="button" class="chatbot-command" data-command="Top labs by bookings">Top labs</button>
            <button type="button" class="chatbot-command" data-command="Asset status summary">Asset status</button>
            <button type="button" class="chatbot-command" data-command="My upcoming bookings">My bookings</button>
            <button type="button" class="chatbot-command" data-command="Pending approvals">Pending approvals</button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="chatbot-message bot">
                <div class="chatbot-bubble">
                    Ask me about total bookings, top labs, asset status, or your upcoming bookings. Type "help" for more.
                </div>
            </div>
        </div>
        <form class="chatbot-input" id="chatbotForm">
            <input type="text" id="chatbotInput" placeholder="Ask about lab data..." autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<script>
(function() {
    const widget = document.getElementById('slamsChatbot');
    if (!widget) {
        return;
    }

    const toggle = document.getElementById('chatbotToggle');
    const navToggle = document.getElementById('chatbotNavToggle');
    const panel = document.getElementById('chatbotPanel');
    const closeBtn = document.getElementById('chatbotClose');
    const form = document.getElementById('chatbotForm');
    const input = document.getElementById('chatbotInput');
    const messages = document.getElementById('chatbotMessages');
    const commands = document.getElementById('chatbotCommands');

    const apiUrl = <?= json_encode(site_url('api/chat')) ?>;
    let csrfTokenName = <?= json_encode($csrfTokenName) ?>;
    let csrfTokenValue = <?= json_encode($csrfTokenHash) ?>;
    const toggles = [toggle, navToggle].filter(Boolean);

    const syncToggleState = (isOpen) => {
        toggles.forEach((button) => {
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            button.setAttribute('aria-label', isOpen ? 'Close chatbot' : 'Open chatbot');
            button.classList.toggle('is-active', isOpen);
        });
    };

    const openPanel = () => {
        widget.classList.add('is-open');
        syncToggleState(true);
        panel.setAttribute('aria-hidden', 'false');
        input.focus();
    };

    const closePanel = () => {
        widget.classList.remove('is-open');
        syncToggleState(false);
        panel.setAttribute('aria-hidden', 'true');
    };

    const addMessage = (text, role) => {
        const item = document.createElement('div');
        item.className = `chatbot-message ${role}`;
        item.innerHTML = `<div class="chatbot-bubble">${text}</div>`;
        messages.appendChild(item);
        messages.scrollTop = messages.scrollHeight;
    };

    const sendMessage = (text) => {
        addMessage(text, 'user');
        input.value = '';
        input.disabled = true;

        const body = new URLSearchParams({
            message: text,
            [csrfTokenName]: csrfTokenValue,
        });

        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        })
        .then((response) => response.json())
        .then((data) => {
            if (data && data.reply) {
                addMessage(data.reply, 'bot');
            } else if (data && (data.message || data.error)) {
                addMessage(data.message || data.error, 'bot');
            } else {
                addMessage('Sorry, I could not process that.', 'bot');
            }

            if (data && data.csrfHash) {
                csrfTokenValue = data.csrfHash;
            }
        })
        .catch(() => {
            addMessage('Something went wrong. Please try again.', 'bot');
        })
        .finally(() => {
            input.disabled = false;
            input.focus();
        });
    };

    toggles.forEach((button) => {
        button.addEventListener('click', () => {
            if (widget.classList.contains('is-open')) {
                closePanel();
            } else {
                openPanel();
            }
        });
    });

    closeBtn.addEventListener('click', closePanel);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) {
            return;
        }

        sendMessage(text);
    });

    if (commands) {
        commands.addEventListener('click', (event) => {
            const button = event.target.closest('.chatbot-command');
            if (!button) {
                return;
            }
            const command = button.getAttribute('data-command');
            if (!command) {
                return;
            }
            if (!widget.classList.contains('is-open')) {
                openPanel();
            }
            sendMessage(command);
        });
    }
})();
</script>
