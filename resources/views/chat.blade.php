<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order Assistant</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

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
            box-shadow: 0 4px 24px rgba(0,0,0,0.1);
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

        .chat-header h1 { font-size: 1.25rem; font-weight: 600; }
        .chat-header p { font-size: 0.85rem; opacity: 0.85; margin-top: 2px; }

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

        textarea:focus { border-color: #667eea; }

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

        button[type="submit"]:hover { opacity: 0.9; }
        button[type="submit"]:disabled { opacity: 0.5; cursor: not-allowed; }

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

        .example-chip:hover { background: #e2e6ea; }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h1>E-commerce Order Assistant</h1>
            <p>Ask about order status, returns, or discounts</p>
        </div>

        <div class="chat-messages" id="messages">
            <div class="message assistant">
                Hello! I can help you with order status, returns, and discount codes. What would you like to know?
            </div>
        </div>

        <div class="chat-footer">
            <div class="examples">
                <span class="example-chip" onclick="fillMessage(this)">What's the status of order ORD-001?</span>
                <span class="example-chip" onclick="fillMessage(this)">I want to return order ORD-002</span>
                <span class="example-chip" onclick="fillMessage(this)">Do you have any discount codes?</span>
            </div>
            <form id="chat-form" class="input-row">
                <textarea
                    id="message"
                    name="message"
                    rows="1"
                    placeholder="Type your message…"
                    required
                    autofocus
                ></textarea>
                <button type="submit" id="send-btn">Send</button>
            </form>
        </div>
    </div>

    <script>
        const form = document.getElementById('chat-form');
        const input = document.getElementById('message');
        const sendBtn = document.getElementById('send-btn');
        const messages = document.getElementById('messages');

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
                const res = await fetch('/chat', {
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

                const data = await res.json();
                thinking.remove();

                if (!res.ok) {
                    addMessage(data.message || 'Something went wrong. Please try again.', 'error');
                } else {
                    addMessage(data.message, 'assistant');
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
