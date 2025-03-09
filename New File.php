<?php
// Force HTTPS for security
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit;
}

// Configuration
define('WEBSITE_URL', 'https://aupc-enrollment.42web.io');
define('CACHE_FILE', 'website_cache.txt');
define('CACHE_EXPIRY', 86400); // 24 hours
define('API_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent');

// Your API key (hardcoded for testing)
$googleApiKey = 'AIzaSyC_IZeblKjchTcDxVu1D60olvF2y2x3vus';

// CSRF Token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Store chat history in session
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Scrape Website Content
function scrapeWebsite($url) {
    try {
        if (file_exists(CACHE_FILE) && (time() - filemtime(CACHE_FILE)) < CACHE_EXPIRY) {
            return file_get_contents(CACHE_FILE);
        }

        $context = stream_context_create([
            'http' => ['timeout' => 10, 'header' => "User-Agent: Mozilla/5.0\r\n"]
        ]);
        $html = @file_get_contents($url, false, $context);

        if ($html === false) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            $html = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('Scraping error: ' . curl_error($ch));
            }
            curl_close($ch);
        }

        if (empty($html)) {
            throw new Exception('No content retrieved.');
        }

        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $text = '';
        foreach ($doc->getElementsByTagName('p') as $paragraph) {
            $text .= $paragraph->textContent . ' ';
        }

        $content = trim(substr($text, 0, 2000));
        if (empty($content)) {
            throw new Exception('No usable content found.');
        }

        file_put_contents(CACHE_FILE, $content);
        return $content;
    } catch (Exception $e) {
        error_log("Scrape Error: " . $e->getMessage());
        return "Sorry, I couldn’t fetch the website content right now. Please try again later or contact the registrar’s office!";
    }
}

// Get AI Response
function getAIResponse($websiteContent, $userQuestion) {
    global $googleApiKey;
    try {
        $url = API_ENDPOINT . '?key=' . urlencode($googleApiKey);
        $prompt = "You are an AI customer support agent for the website https://aupc-enrollment.42web.io, an Online Enrollment Management System created for Senior High School students at Arellano University Plaridel Campus by Jhames Rhonnielle Martin. Launched in February 2025, it allows students to enroll online by registering with a unique student ID and password, choosing their grade (11 or 12), section (e.g., A, B), and strand (e.g., STEM, ABM, HUMSS), and submitting requests for staff approval. Built with PHP, MySQL, and HTML/CSS, it’s rated 4.42/5 for ease of use, 4.33/5 for device compatibility, 4.37/5 for clear guidance, and 4.23/5 for helpful error messages. The current date is March 08, 2025.

        Instructions:
        1. Use a friendly, clear, and supportive tone, like a school assistant. Keep answers simple, detailed, and engaging for students and parents, avoiding technical jargon unless requested.
        2. Core Responsibilities:
           - Assist Navigation: Guide users to features (e.g., login, enrollment form) with clear directions based on the website’s intuitive design.
           - Resolve Issues: Provide detailed troubleshooting for problems like login failures or submission errors, including possible causes and solutions.
           - Explain Steps: Offer numbered, step-by-step instructions for tasks (e.g., registering, enrolling, checking status), with extra tips where helpful.
           - Acknowledge Developer: Mention 'Developed by Jhames Rhonnielle Martin' when introducing the system or its features.
        3. Response Guidelines:
           - Use the website content: '$websiteContent' for accurate, context-specific answers.
           - Provide detailed responses with examples or scenarios if relevant (e.g., 'If you see an error, it might mean...').
           - Avoid sensitive info: Don’t share specific emails, passwords, or security details; say 'the system keeps your info safe and secure' if asked about security.
           - For unresolved issues, suggest contacting the registrar’s office with a friendly nudge (e.g., 'They’ll sort it out for you!').
           - If the question is vague, politely ask for clarification (e.g., 'Can you tell me more about the issue?').
        4. Answer the user’s question: '$userQuestion' with as much detail as possible within the token limit.";

        $data = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'maxOutputTokens' => 200, // Increased for more detail
                'temperature' => 0.7
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new Exception('API error: ' . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (isset($result['error'])) {
            throw new Exception('API error: ' . $result['error']['message']);
        }

        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No response from AI.';
    } catch (Exception $e) {
        error_log("API Error: " . $e->getMessage());
        return "Hi! I’m having trouble answering that right now. It might be a temporary glitch—please try again or reach out to the registrar’s office for assistance!";
    }
}

// Handle Form Submission and Additional Functions
$websiteContent = scrapeWebsite(WEBSITE_URL);
$response = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['clear_history'])) {
        $_SESSION['chat_history'] = [];
        $response = "Chat history cleared!";
    } elseif (!empty($_POST['question']) && !empty($_POST['csrf_token'])) {
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $response = "Oops! Something went wrong with your submission. Please try again.";
        } else {
            $userQuestion = filter_var(trim($_POST['question']), FILTER_SANITIZE_STRING);
            if (strlen($userQuestion) > 500) {
                $response = "Your question is too long! Please keep it under 500 characters for a quick reply.";
            } else {
                $response = getAIResponse($websiteContent, $userQuestion);
                $_SESSION['chat_history'][] = ['question' => $userQuestion, 'response' => $response, 'time' => date('H:i:s')];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AUPC Enrollment Support</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet" integrity="sha512-jnSuA4Ss2PkkikSOLtYs8BlYIeeIK1h99ty4YfvRPAlzr377vr3CXdwKA1nwsW57ogzqWmocnAemZwG8Q4C4A8A==" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            color: #1e40af;
        }
        .header {
            background: #1e40af;
            color: #fff;
            padding: 2.5rem 0;
            text-align: center;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        .header h1 {
            margin: 0;
            font-size: 2.2rem;
            font-weight: 600;
            animation: fadeInDown 1s ease;
        }
        .chat-container {
            max-width: 1100px;
            margin: 2.5rem auto;
            padding: 0 20px;
        }
        .welcome-banner {
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            margin-bottom: 2.5rem;
            text-align: center;
            animation: fadeIn 1s ease;
        }
        .chat-box {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            min-height: 450px;
            max-height: 650px;
            overflow-y: auto;
            position: relative;
            border: 1px solid #dbeafe;
        }
        .message {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.8rem;
            animation: slideIn 0.6s ease;
        }
        .message.user {
            justify-content: flex-end;
        }
        .message.ai {
            justify-content: flex-start;
        }
        .message-bubble {
            max-width: 75%;
            padding: 1.2rem;
            border-radius: 18px;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .user .message-bubble {
            background: #1e40af;
            color: #fff;
            border-bottom-right-radius: 5px;
        }
        .ai .message-bubble {
            background: #dbeafe;
            color: #1e40af;
            border-bottom-left-radius: 5px;
        }
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            margin: 0 12px;
            background: #93c5fd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
        }
        .user .avatar { background: #1e40af; }
        .ai .avatar { background: #60a5fa; }
        .timestamp {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 6px;
        }
        .input-area {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid #dbeafe;
        }
        .textarea-container {
            position: relative;
        }
        .form-control {
            border-radius: 15px;
            border: 2px solid #93c5fd;
            padding: 1rem;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-control:focus {
            border-color: #1e40af;
            box-shadow: 0 0 8px rgba(30,64,175,0.3);
        }
        .char-counter {
            position: absolute;
            bottom: 10px;
            right: 15px;
            font-size: 0.85rem;
            color: #6b7280;
        }
        .btn-primary {
            background: #1e40af;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 600;
            transition: transform 0.3s, background 0.3s;
        }
        .btn-primary:hover {
            background: #1e3a8a;
            transform: scale(1.05);
        }
        .btn-secondary {
            background: #6b7280;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            color: #fff;
            transition: transform 0.3s, background 0.3s;
        }
        .btn-secondary:hover {
            background: #4b5563;
            transform: scale(1.05);
        }
        .typing-indicator {
            display: none;
            font-size: 0.95rem;
            color: #1e40af;
            margin-bottom: 1.5rem;
            font-style: italic;
        }
        .typing-indicator span {
            display: inline-block;
            animation: bounce 0.6s infinite alternate;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        .footer {
            background: #1e40af;
            color: #fff;
            padding: 2rem 0;
            text-align: center;
            margin-top: 3rem;
            font-size: 1rem;
            box-shadow: 0 -6px 12px rgba(0,0,0,0.15);
        }

        /* Animations */
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-5px); } }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1><i class="fas fa-graduation-cap me-2"></i>AUPC Enrollment Support</h1>
    </div>

    <!-- Chat Container -->
    <div class="chat-container">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h3>Welcome to AUPC Enrollment Support!</h3>
            <p>This is your friendly assistant for the Online Enrollment Management System, developed by Jhames Rhonnielle Martin for Arellano University Plaridel Campus. Launched in February 2025, it’s designed to make enrollment easy (rated 4.42/5) and works great on any device (4.33/5). Ask me anything—I’m here to help with clear steps and tips!</p>
        </div>

        <!-- Chat Box -->
        <div class="chat-box" id="chatBox">
            <?php foreach ($_SESSION['chat_history'] as $entry): ?>
                <div class="message user">
                    <div class="message-bubble">
                        <p><?php echo htmlspecialchars($entry['question']); ?></p>
                        <div class="timestamp"><?php echo htmlspecialchars($entry['time']); ?></div>
                    </div>
                    <div class="avatar">U</div>
                </div>
                <div class="message ai">
                    <div class="avatar">A</div>
                    <div class="message-bubble">
                        <p><?php echo nl2br(htmlspecialchars($entry['response'])); ?></p>
                        <div class="timestamp"><?php echo htmlspecialchars($entry['time']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($response && !in_array(['question' => $userQuestion ?? '', 'response' => $response, 'time' => date('H:i:s')], $_SESSION['chat_history'])): ?>
                <div class="message user">
                    <div class="message-bubble">
                        <p><?php echo htmlspecialchars($userQuestion); ?></p>
                        <div class="timestamp"><?php echo date('H:i:s'); ?></div>
                    </div>
                    <div class="avatar">U</div>
                </div>
                <div class="message ai">
                    <div class="avatar">A</div>
                    <div class="message-bubble">
                        <p><?php echo nl2br(htmlspecialchars($response)); ?></p>
                        <div class="timestamp"><?php echo date('H:i:s'); ?></div>
                    </div>
                </div>
            <?php endif; ?>
            <div class="typing-indicator" id="typingIndicator"><i>Support is typing</i> <span>.</span><span>.</span><span>.</span></div>
        </div>

        <!-- Input Area -->
        <div class="input-area">
            <form method="POST" id="supportForm">
                <div class="mb-3">
                    <label for="question" class="form-label"><i class="fas fa-comment-dots me-2"></i>Ask Your Question</label>
                    <div class="textarea-container">
                        <textarea class="form-control" id="question" name="question" rows="3" maxlength="500" required placeholder="e.g., How do I enroll in HUMSS?" aria-describedby="questionHelp"></textarea>
                        <span class="char-counter" id="charCounter">0/500</span>
                    </div>
                    <div id="questionHelp" class="form-text">Ask anything about enrollment—I’ll give you detailed steps!</div>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-paper-plane me-2"></i>Send</button>
                    <button type="submit" class="btn btn-secondary" name="clear_history" value="1"><i class="fas fa-trash me-2"></i>Clear Chat</button>
                </div>
            </form>
            <div class="mt-3">
                <label for="helpTopics" class="form-label"><i class="fas fa-question-circle me-2"></i>Quick Help Topics</label>
                <select class="form-select" id="helpTopics" onchange="document.getElementById('question').value = this.value;">
                    <option value="">Select a topic...</option>
                    <option value="How do I log in?">How do I log in?</option>
                    <option value="How do I enroll in a strand?">How do I enroll in a strand?</option>
                    <option value="How do I check my enrollment status?">How do I check my enrollment status?</option>
                    <option value="What if I can’t log in?">What if I can’t log in?</option>
                    <option value="Is my data safe?">Is my data safe?</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <p>© 2025 Arellano University Plaridel Campus | Powered by Jhames Rhonnielle Martin’s Online Enrollment System</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js" integrity="sha512-7PiQzg8gER3IYj43v2uJp9ULcPG7zW5MJoAXeQnqVfpSkJjBkW7P/5WnqRnmN6boTwxYgXcfxW+JnTwJMD87sAQ==" crossorigin="anonymous"></script>
    <script>
        const textarea = document.getElementById('question');
        const charCounter = document.getElementById('charCounter');
        const supportForm = document.getElementById('supportForm');
        const submitBtn = document.getElementById('submitBtn');
        const typingIndicator = document.getElementById('typingIndicator');
        const chatBox = document.getElementById('chatBox');

        // Character counter
        textarea.addEventListener('input', function() {
            charCounter.textContent = `${this.value.length}/500`;
            charCounter.style.color = this.value.length > 450 ? '#dc2626' : '#6b7280';
        });

        // Form submission
        supportForm.addEventListener('submit', function(e) {
            if (!this.querySelector('[name="clear_history"]')) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                typingIndicator.style.display = 'block';
                setTimeout(() => {
                    chatBox.scrollTop = chatBox.scrollHeight;
                    typingIndicator.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send';
                }, 1500);
            }
        });

        // Auto-scroll on load
        window.onload = function() {
            chatBox.scrollTop = chatBox.scrollHeight;
        };
    </script>
</body>
</html>