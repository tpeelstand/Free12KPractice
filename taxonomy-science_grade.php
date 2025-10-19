<?php
get_header();

$term = get_queried_object();
$skills = get_posts(array(
    'post_type' => 'math_skill',
    'posts_per_page' => -1,
    'tax_query' => array(
        array(
            'taxonomy' => 'math_grade',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ),
    ),
));
$video_link = function_exists('get_field') ? get_field('video_link', 'math_grade_' . $term->term_id) : '';

// Organize questions by difficulty
$questions_by_difficulty = array(
    'easy' => array(),
    'medium' => array(),
    'hard' => array(),
    'mastery' => array()
);

foreach ($skills as $q) {
    $answer_terms = wp_get_post_terms($q->ID, 'answer', array('fields' => 'names'));
    $answer = !empty($answer_terms) ? $answer_terms[0] : '';
    $explanation = get_post_field('post_excerpt', $q);
    $difficulty_terms = wp_get_post_terms($q->ID, 'math_difficulty', array('fields'=>'names'));
    
    $question_obj = array(
        'title'       => get_the_title($q),
        'question'    => get_post_field('post_content', $q),
        'answer'      => $answer,
        'explanation' => $explanation,
        'difficulty'  => $difficulty_terms,
        'id'          => $q->ID,
    );
    
    // Categorize by difficulty (case-insensitive)
    if (!empty($difficulty_terms)) {
        $diff_lower = strtolower($difficulty_terms[0]);
        if (isset($questions_by_difficulty[$diff_lower])) {
            $questions_by_difficulty[$diff_lower][] = $question_obj;
        }
    }
}

// Shuffle each difficulty pool
shuffle($questions_by_difficulty['easy']);
shuffle($questions_by_difficulty['medium']);
shuffle($questions_by_difficulty['hard']);
shuffle($questions_by_difficulty['mastery']);
?>

<style>
    /* Timer Styles */
    .timer-container {
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 15px 0;
        padding: 12px;
        background: #f8f9fa;
        border-radius: 6px;
        border: 1px solid #dee2e6;
    }

    .timer-display {
        font-size: 20px;
        font-weight: 600;
        color: #495057;
        font-family: 'Courier New', monospace;
    }

    .timer-label {
        margin-right: 10px;
        color: #6c757d;
        font-weight: 500;
    }

    .timer-icon {
        margin-right: 8px;
        color: #0073aa;
    }

    /* Completion message with timer */
    .timer-final {
        display: inline-block;
        background: #0073aa;
        color: white;
        padding: 5px 15px;
        border-radius: 4px;
        margin-left: 10px;
        font-weight: bold;
    }

    .quiz-stats {
        margin-top: 20px;
        padding: 20px;
        background: #f0f8ff;
        border-radius: 8px;
    }

    .quiz-stats h3 {
        margin-top: 0;
        color: #0073aa;
    }

    .quiz-stats ul {
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }

    .quiz-stats li {
        padding: 5px 0;
        font-size: 16px;
    }
</style>

<div class="container">
    <div class="edu-learning-container">
        <!-- Left Aside -->
        <aside class="edu-sidebar">
            <?php if ($video_link): ?>
            <div class="edu-video-section">
                <h3 class="video-title">Learning Video</h3>
                <div class="video-wrapper">
                    <iframe 
                        src="<?php echo esc_url($video_link); ?>" 
                        frameborder="0" 
                        allowfullscreen
                        title="Educational Video">
                    </iframe>
                </div>
            </div>
            <?php endif; ?>

            <nav class="edu-navigation">
                <div class="progress-tracker">
                    <h4 class="progress-title">Your Progress</h4>
                    <div class="js-progress-bar">
                        <div class="progress-fill" id="js-progress-fill" style="width: 0%;"></div>
                    </div>

                    <!-- Timer Component -->
                    <div class="timer-container">
                        <span class="timer-icon">⏱️</span>
                        <span class="timer-label">Time Elapsed:</span>
                        <span class="timer-display" id="js-timer-display">00:00</span>
                    </div>

                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <span class="progress-text" id="js-progress-text">0% Complete</span>
                        <span class="score-text" id="js-score-text" style="margin-left:auto;font-weight:600;color:#0073aa;">Score: 0</span>
                    </div>
                </div>
            </nav>
        </aside>

        <main class="edu-main-content">
            <div class="learning-module">
                <div id="js-question-area"></div>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    // Timer Class
    class QuizTimer {
        constructor() {
            this.startTime = null;
            this.endTime = null;
            this.timerInterval = null;
            this.isRunning = false;
            this.timerDisplay = document.getElementById('js-timer-display');
        }

        start() {
            if (!this.isRunning) {
                this.startTime = Date.now();
                this.isRunning = true;
                this.timerInterval = setInterval(() => this.updateDisplay(), 1000);
                console.log('Timer started at:', new Date(this.startTime).toLocaleTimeString());
            }
        }

        stop() {
            if (this.isRunning) {
                this.endTime = Date.now();
                this.isRunning = false;
                clearInterval(this.timerInterval);
                const finalTime = this.getFormattedTime();
                console.log('Timer stopped. Final time:', finalTime);
                return finalTime;
            }
            return this.getFormattedTime();
        }

        updateDisplay() {
            if (this.isRunning && this.startTime) {
                const elapsed = Date.now() - this.startTime;
                this.timerDisplay.textContent = this.formatTime(elapsed);
            }
        }

        formatTime(milliseconds) {
            const totalSeconds = Math.floor(milliseconds / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;
            return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        getFormattedTime() {
            if (this.startTime && this.endTime) {
                return this.formatTime(this.endTime - this.startTime);
            } else if (this.startTime) {
                return this.formatTime(Date.now() - this.startTime);
            }
            return '00:00';
        }

        reset() {
            this.stop();
            this.startTime = null;
            this.endTime = null;
            if (this.timerDisplay) {
                this.timerDisplay.textContent = '00:00';
            }
        }
    }

    // Initialize timer
    const quizTimer = new QuizTimer();
    let timerStarted = false;
    let correctAnswers = 0;
    let incorrectAnswers = 0;

    // Question pools by difficulty
    const questionPools = {
        easy: <?php echo json_encode($questions_by_difficulty['easy']); ?>,
        medium: <?php echo json_encode($questions_by_difficulty['medium']); ?>,
        hard: <?php echo json_encode($questions_by_difficulty['hard']); ?>,
        mastery: <?php echo json_encode($questions_by_difficulty['mastery']); ?>
    };

    // Select questions: 5 easy, 5 medium, 5 hard, 5 mastery
    let selectedQuestions = [];
    selectedQuestions = selectedQuestions.concat(questionPools.easy.slice(0, 5));
    selectedQuestions = selectedQuestions.concat(questionPools.medium.slice(0, 5));
    selectedQuestions = selectedQuestions.concat(questionPools.hard.slice(0, 5));
    selectedQuestions = selectedQuestions.concat(questionPools.mastery.slice(0, 5));

    // Track remaining questions in pools for replacements
    const remainingPools = {
        easy: questionPools.easy.slice(5),
        medium: questionPools.medium.slice(5),
        hard: questionPools.hard.slice(5),
        mastery: questionPools.mastery.slice(5)
    };

    let current = 0;
    let score = 0;
    let questionsAnswered = 0; // Track only answered questions for progress
    const totalQuestions = 20;
    const pointsPerQuestion = 5;

    function getDifficultyLabel(difficultyArr) {
        if (!difficultyArr || !difficultyArr.length) return '';
        let levels = ['Easy', 'Medium', 'Hard', 'Mastery'];
        let lowerDifficulties = difficultyArr.map(d => d.toLowerCase());
        
        return levels.map(level => {
            let active = lowerDifficulties.includes(level.toLowerCase()) ? 'active' : '';
            return `<span class="level ${active}" data-level="${level.toLowerCase()}">${level}</span>`;
        }).join('');
    }

    function updateProgress() {
        let percent = Math.round((questionsAnswered / totalQuestions) * 100);
        
        const progressFill = document.getElementById('js-progress-fill');
        const progressText = document.getElementById('js-progress-text');
        
        if (progressFill) {
            progressFill.style.setProperty('width', percent + '%', 'important');
            progressFill.offsetHeight;
        }
        
        if (progressText) {
            progressText.textContent = `${percent}% Complete`;
        }
        
        const scoreText = document.getElementById('js-score-text');
        if (scoreText) {
            scoreText.textContent = `Score: ${score}`;
        }

        // Check if quiz is complete (20 questions answered)
        if (questionsAnswered >= totalQuestions) {
            const finalTime = quizTimer.stop();
            showCompletionScreen(finalTime);
        }
    }

    function showCompletionScreen(finalTime) {
        const accuracy = correctAnswers > 0 ? Math.round((correctAnswers / questionsAnswered) * 100) : 0;
        
        document.getElementById('js-question-area').innerHTML = `
            <h2 class="completed">🎉 Quiz Complete!</h2>
            <p class="final-score">Your final score: <strong>${score}</strong></p>
            <p class="final-time">Time taken: <span class="timer-final">${finalTime}</span></p>
            
            <div class="quiz-stats">
                <h3>📊 Quiz Statistics</h3>
                <ul>
                    <li>✅ Correct Answers: ${correctAnswers}</li>
                    <li>❌ Incorrect Answers: ${incorrectAnswers}</li>
                    <li>📝 Total Questions: ${questionsAnswered}</li>
                    <li>🎯 Accuracy: ${accuracy}%</li>
                    <li>⏱️ Total Time: ${finalTime}</li>
                    <li>🏆 Final Score: ${score} points</li>
                    <li>📈 Average: ${(score / questionsAnswered).toFixed(1)} points per question</li>
                </ul>
            </div>
            
            <button onclick="location.reload()" style="margin-top:20px;padding:10px 30px;background:#0073aa;color:white;border:none;border-radius:6px;font-size:16px;cursor:pointer;">
                Start New Quiz
            </button>
        `;
        
        // Save results if needed (optional AJAX call)
        if (typeof saveQuizResults === 'function') {
            saveQuizResults(finalTime, score);
        }
    }

    function getReplacementQuestion(difficulty) {
        const pool = remainingPools[difficulty];
        if (pool && pool.length > 0) {
            return pool.shift(); // Remove and return first question from pool
        }
        return null;
    }

    function showQuestion(idx) {
        // Check if quiz is already complete
        if (questionsAnswered >= totalQuestions) {
            const finalTime = quizTimer.getFormattedTime();
            showCompletionScreen(finalTime);
            return;
        }

        if (!selectedQuestions[idx]) {
            // If we've run out of questions but haven't answered 20 yet
            if (questionsAnswered < totalQuestions) {
                // Recycle questions if needed
                if (idx >= selectedQuestions.length && selectedQuestions.length > 0) {
                    current = 0;
                    showQuestion(0);
                    return;
                }
            } else {
                const finalTime = quizTimer.getFormattedTime();
                showCompletionScreen(finalTime);
                return;
            }
        }

        const q = selectedQuestions[idx].question.trim();
        const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(q);
        let difficulty = getDifficultyLabel(selectedQuestions[idx].difficulty);

        document.getElementById('js-question-area').innerHTML = `
            <div class="question-header">
                <h2 class="question-title">Question ${questionsAnswered + 1} of ${totalQuestions}</h2>
                <div class="skill-level">
                    <span class="skill-label">Skill Level:</span>
                    <div class="skill-indicator">
                        ${difficulty}
                    </div>
                </div>
            </div>
            <div class="question-content">
                <p class="question-text">
                    ${isImage
                        ? `<img src="${q}" alt="Question Image" style="max-width:400px;height:auto;">`
                        : q
                    }
                </p>
            </div>
            <div class="feedback-area" id="feedbackArea" style="display: none;">
                <div class="feedback-content"></div>
            </div>
            <form class="answer-form" id="learningAnswerForm">
                <div class="answer-wrapper">
                    <label for="userAnswer" class="answer-label">Your Answer:</label>
                    <input type="text" id="userAnswer" name="userAnswer" class="answer-input" placeholder="Enter your answer here..." required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">
                        <span class="btn-text">Submit Answer</span>
                        <span class="btn-icon">→</span>
                    </button>
                    <button type="button" class="btn btn-skip">
                        Skip Question
                    </button>
                </div>
            </form>
        `;

        const answerForm = document.getElementById('learningAnswerForm');
        const skipBtn = document.querySelector('.btn-skip');

        if (answerForm) {
            answerForm.onsubmit = function(e) {
                e.preventDefault();

                // Start timer on first submit
                if (!timerStarted) {
                    timerStarted = true;
                    quizTimer.start();
                }

                let userAnswer = document.getElementById('userAnswer').value.trim();
                let correctAnswer = selectedQuestions[idx].answer.trim();
                let explanation = selectedQuestions[idx].explanation ? selectedQuestions[idx].explanation.trim() : '';
                
                let feedback = '';
                let isCorrect = userAnswer.toLowerCase() === correctAnswer.toLowerCase();
                
                questionsAnswered++; // Always increment for both correct and incorrect
                
                if (isCorrect) {
                    score += pointsPerQuestion;
                    correctAnswers++;
                    feedback = '<p style="color:#00A651;font-weight:600;font-size: 2rem;"><i class="fas fa-check"></i> Correct! +' + pointsPerQuestion + ' points.</p>';
                    
                    if (explanation) {
                        feedback += '<div style="border:2px solid goldenrod;font-size:1.4rem;margin-top:8px;padding: 12px 24px;color: black;border-radius:8px;"><strong><i class="fas fa-info-circle"></i> Explanation:</strong> ' + explanation + '</div>';
                    }
                    
                    // Check if this was the last question
                    if (questionsAnswered >= totalQuestions) {
                        feedback += '<button id="viewResultsBtn" class="btn btn-view-results" style="margin-top:18px;font-size:1.25rem;padding:8px 20px;background:#00A651;color:#fff;border:none;border-radius:6px;cursor:pointer;">View Final Results >></button>';
                    } else {
                        feedback += '<button id="nextQuestionBtn" class="btn btn-next-question" style="margin-top:18px;font-size:1.25rem;padding:8px 20px;background:#034CA8;color:#fff;border:none;border-radius:6px;cursor:pointer;">Next Question >></button>';
                    }
                    
                } else {
                    score -= 5; // Deduct 5 points for incorrect answer
                    incorrectAnswers++;
                    feedback = '<p style="color:#e53935;font-weight:600;font-size: 2rem;"><i class="fas fa-times"></i> Incorrect. -5 points.</p><br>';
                    feedback += '<div style="color: black;margin-top:2px;"><strong>Correct Answer:</strong> ' + correctAnswer + '</div>';
                    if (explanation) {
                        feedback += '<div style="border:2px solid goldenrod;font-size:1.4rem;margin-top:8px;padding: 12px 24px;color: black;border-radius:8px;"><strong><i class="fas fa-info-circle"></i> Explanation:</strong> ' + explanation + '</div>';
                    }
                    
                    // Check if this was the last question
                    if (questionsAnswered >= totalQuestions) {
                        feedback += '<button id="viewResultsBtn" class="btn btn-view-results" style="margin-top:18px;font-size:1.25rem;padding:8px 20px;background:#00A651;color:#fff;border:none;border-radius:6px;cursor:pointer;">View Final Results >></button>';
                    } else {
                        feedback += '<button id="nextQuestionBtn" class="btn btn-next-question" style="margin-top:18px;font-size:1.25rem;padding:8px 20px;background:#034CA8;color:#fff;border:none;border-radius:6px;cursor:pointer;">Next Question >></button>';
                    }
                }

                var submitBtn = document.querySelector('.btn-submit');
                var skipBtnDisable = document.querySelector('.btn-skip');
                if (submitBtn) submitBtn.disabled = true;
                if (skipBtnDisable) skipBtnDisable.disabled = true;
                
                document.querySelector('#feedbackArea .feedback-content').innerHTML = feedback;
                document.getElementById('feedbackArea').style.display = 'block';
                
                // Update score display immediately
                updateProgress();
                
                // Handle next button or view results button
                var nextBtn = document.getElementById('nextQuestionBtn');
                var resultsBtn = document.getElementById('viewResultsBtn');
                
                if (nextBtn) {
                    nextBtn.onclick = function() {
                        // For correct answers, remove the question
                        // For incorrect answers, move it to the back of the queue
                        if (isCorrect) {
                            selectedQuestions.splice(current, 1);
                            // current stays the same since we removed an element
                        } else {
                            let wrong = selectedQuestions.splice(current, 1)[0];
                            selectedQuestions.push(wrong);
                            // current stays the same since we removed and added
                        }
                        showQuestion(current);
                    };
                }
                
                if (resultsBtn) {
                    resultsBtn.onclick = function() {
                        updateProgress(); // This will trigger the completion screen
                    };
                }
            };
        }

        if (skipBtn) {
            skipBtn.onclick = function() {
                // Get difficulty of skipped question
                let skippedQuestion = selectedQuestions[current];
                let difficulty = skippedQuestion.difficulty[0].toLowerCase();
                
                // Try to replace with a new question of same difficulty
                let replacement = getReplacementQuestion(difficulty);
                
                if (replacement) {
                    // Replace current question with new one
                    selectedQuestions[current] = replacement;
                    console.log('Replaced skipped question with new ' + difficulty + ' question');
                } else {
                    // No replacement available, just move to next
                    current++;
                    if (current >= selectedQuestions.length) {
                        current = 0; // Loop back to beginning
                    }
                }
                
                // Don't increment questionsAnswered or score
                // Don't start timer on skip
                updateProgress();
                showQuestion(current);
            };
        }

        updateProgress();
    }

    if (selectedQuestions.length > 0) {
        showQuestion(0);
    } else {
        document.getElementById('js-question-area').innerHTML = `<h2>No questions found for this grade.</h2>`;
        updateProgress();
    }

    // Optional: Save quiz data to database via AJAX when complete
    window.saveQuizResults = function(finalTime, finalScore) {
        // You can implement an AJAX call here to save results to WordPress database
        if (typeof jQuery !== 'undefined') {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'save_quiz_results',
                    nonce: '<?php echo wp_create_nonce('quiz_nonce'); ?>',
                    grade_id: <?php echo $term->term_id; ?>,
                    subject: 'science',
                    score: finalScore,
                    time_taken: finalTime,
                    questions_answered: questionsAnswered,
                    correct_answers: correctAnswers,
                    incorrect_answers: incorrectAnswers
                },
                success: function(response) {
                    console.log('Quiz results saved:', response);
                },
                error: function(error) {
                    console.error('Failed to save results:', error);
                }
            });
        }
    };
})();
</script>

<?php get_footer(); ?>