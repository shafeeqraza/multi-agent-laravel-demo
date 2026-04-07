<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order Assistant</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .chat-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 700px;
            display: flex;
            flex-direction: column;
            height: 80vh;
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px 24px;
        }

        .chat-header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .chat-header p {
            font-size: 0.85rem;
            opacity: 0.85;
            margin-top: 2px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.95rem;
            line-height: 1.5;
            word-break: break-word;
        }

        .message.user {
            align-self: flex-end;
            background: #667eea;
            color: #fff;
            border-bottom-right-radius: 4px;
        }

        .message.assistant {
            align-self: flex-start;
            background: #f1f3f5;
            color: #1a1a2e;
            border-bottom-left-radius: 4px;
        }

        .message.error {
            align-self: flex-start;
            background: #fff0f0;
            color: #c0392b;
            border: 1px solid #ffc8c8;
            border-bottom-left-radius: 4px;
        }

        .message.thinking {
            align-self: flex-start;
            background: #f1f3f5;
            color: #888;
            font-style: italic;
        }

        .chat-footer {
            padding: 16px 20px;
            border-top: 1px solid #e9ecef;
            background: #fff;
        }

        .input-row {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        textarea {
            flex: 1;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.95rem;
            font-family: inherit;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
            max-height: 120px;
        }

        textarea:focus {
            border-color: #667eea;
        }

        button[type="submit"] {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 10px 22px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            white-space: nowrap;
            height: 44px;
        }

        button[type="submit"]:hover {
            opacity: 0.9;
        }

        button[type="submit"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .examples {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 10px;
        }

        .example-chip {
            background: #f1f3f5;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.8rem;
            color: #555;
            cursor: pointer;
            transition: background 0.15s;
        }

        .example-chip:hover {
            background: #e2e6ea;
        }

        .chat-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .user-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 0.8rem;
            opacity: 0.95;
        }

        .user-bar a {
            color: #fff;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .user-bar button {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.8rem;
            cursor: pointer;
        }

        .user-bar button:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .message.auth-prompt {
            align-self: flex-start;
            background: #fff8e6;
            color: #7d5a00;
            border: 1px solid #f0d78c;
            border-bottom-left-radius: 4px;
        }

        .auth-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-modal-backdrop.is-open {
            display: flex;
        }

        .auth-modal {
            background: #fff;
            border-radius: 14px;
            max-width: 420px;
            width: 100%;
            padding: 24px;
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .auth-modal h2 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #1a1a2e;
        }

        .auth-modal p {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #444;
            margin-bottom: 18px;
        }

        .auth-modal-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .auth-modal-actions a,
        .auth-modal-actions button {
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: none;
        }

        .auth-modal-actions .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }

        .auth-modal-actions .btn-secondary {
            background: #e9ecef;
            color: #333;
        }
    </style>
</head>

<body>
    <div class="chat-container">
        <div class="chat-header">
            <div class="chat-header-top">
                <div>
                    <h1>E-commerce Order Assistant</h1>
                    @auth
                        <p>Signed in — we can look up your recent orders and order history when you ask.</p>
                    @else
                        <p>Browsing as a guest — log in to ask about your personal orders or order history.</p>
                    @endauth
                </div>
                @auth
                    <div class="user-bar">
                        <span>{{ auth()->user()->name ?? auth()->user()->email }}</span>
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                        <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                            @csrf
                            <button type="submit">Log out</button>
                        </form>
                    </div>
                @endauth
            </div>
        </div>

        <div class="chat-messages" id="messages">
            <div class="message assistant">
                @auth
                    Hello, {{ auth()->user()->name ?? auth()->user()->email }}! I can help with order status, your recent orders, returns, and discount codes. What would you like to know?
                @else
                    Hello! I can help with general order questions (if you have an order number), returns, and discount codes. To see your own recent orders or history, please log in first.
                @endauth
            </div>
        </div>

        <div class="chat-footer">
            <div class="examples">
                <span class="example-chip" onclick="fillMessage(this)">What's the status of order ORD-001?</span>
                <span class="example-chip" onclick="fillMessage(this)">What is my latest order?</span>
                <span class="example-chip" onclick="fillMessage(this)">I want to return order ORD-002</span>
                <span class="example-chip" onclick="fillMessage(this)">Do you have any discount codes?</span>
            </div>
            <form id="chat-form" class="input-row">
                <textarea id="message" name="message" rows="1" placeholder="Type your message…" required
                    autofocus></textarea>
                <button type="submit" id="send-btn">Send</button>
            </form>
        </div>
    </div>

    <div class="auth-modal-backdrop" id="auth-modal" role="dialog" aria-modal="true" aria-labelledby="auth-modal-title">
        <div class="auth-modal">
            <h2 id="auth-modal-title">Sign in required</h2>
            <p id="auth-modal-text"></p>
            <div class="auth-modal-actions">
                <button type="button" class="btn-secondary" id="auth-modal-dismiss">Not now</button>
                <a class="btn-primary" id="auth-modal-login" href="{{ route('login') }}">Log in</a>
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('chat-form');
        const input = document.getElementById('message');
        const sendBtn = document.getElementById('send-btn');
        const messages = document.getElementById('messages');
        const chatPostUrl = @json(route('chat'));
        const authModal = document.getElementById('auth-modal');
        const authModalText = document.getElementById('auth-modal-text');
        const authModalDismiss = document.getElementById('auth-modal-dismiss');

        function openAuthModal(text) {
            authModalText.textContent = text;
            authModal.classList.add('is-open');
        }

        function closeAuthModal() {
            authModal.classList.remove('is-open');
        }

        authModalDismiss.addEventListener('click', closeAuthModal);
        authModal.addEventListener('click', function (e) {
            if (e.target === authModal) {
                closeAuthModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAuthModal();
            }
        });

        function fillMessage(chip) {
            input.value = chip.textContent;
            input.focus();
        }

        function addMessage(text, type) {
            const div = document.createElement('div');
            div.className = `message ${type}`;
            div.textContent = text;
            messages.appendChild(div);
            messages.scrollTop = messages.scrollHeight;
            return div;
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            const text = input.value.trim();
            if (!text) return;

            addMessage(text, 'user');
            input.value = '';
            sendBtn.disabled = true;

            const thinking = addMessage('Thinking…', 'thinking');

            try {
                const res = await fetch(chatPostUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ message: text }),
                });

                const data = await res.json().catch(() => ({}));
                thinking.remove();

                if (res.status === 401 || res.status === 419) {
                    addMessage('Your session expired. Please refresh the page and sign in again.', 'error');
                } else if (!res.ok) {
                    const errText =
                        data.report?.error?.user_message ??
                        data.message ??
                        'Something went wrong. Please try again.';
                    addMessage(errText, 'error');
                } else if (data.auth_required === true) {
                    const authMsg =
                        typeof data.reply === 'string' && data.reply !== ''
                            ? data.reply
                            : 'Please log in first so I can access your recent orders.';
                    addMessage(authMsg, 'auth-prompt');
                    openAuthModal(authMsg);
                } else {
                    const assistantText =
                        typeof data.reply === 'string' && data.reply !== '' && data.auth_required !== true
                            ? data.reply
                            : (data.message ??
                                data.result?.message ??
                                'Thanks — your request was completed.');
                    addMessage(assistantText, 'assistant');
                }
            } catch (err) {
                thinking.remove();
                addMessage('Network error. Please check your connection.', 'error');
            } finally {
                sendBtn.disabled = false;
                input.focus();
            }
        });
    </script>
</body>

</html>
