<?php
get_header();

$term = get_queried_object();
$skills = get_posts(array(
    'post_type' => 'english_skill',
    'posts_per_page' => -1,
    'tax_query' => array(
        array(
            'taxonomy' => 'english_grade',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ),
    ),
));
$video_link = function_exists('get_field') ? get_field('video_link', 'english_grade_' . $term->term_id) : '';

// Organize questions by difficulty
$questions_by_difficulty = array(
    'easy' => array(),
    'medium' => array(),
    'difficult' => array()
);

foreach ($skills as $q) {
    $answer_terms = wp_get_post_terms($q->ID, 'english_answer', array('fields' => 'names'));
    $answer = !empty($answer_terms) ? $answer_terms[0] : '';
    $explanation = get_post_field('post_excerpt', $q);
    $difficulty_terms = wp_get_post_terms($q->ID, 'english_difficulty', array('fields'=>'names'));
    
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
shuffle($questions_by_difficulty['difficult']);
?>

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
    // Question pools by difficulty
    const questionPools = {
        easy: <?php echo json_encode($questions_by_difficulty['easy']); ?>,
        medium: <?php echo json_encode($questions_by_difficulty['medium']); ?>,
        difficult: <?php echo json_encode($questions_by_difficulty['difficult']); ?>
    };

    // Select questions: 6 easy, 7 medium, 7 difficult
    let selectedQuestions = [];
    selectedQuestions = selectedQuestions.concat(questionPools.easy.slice(0, 6));
    selectedQuestions = selectedQuestions.concat(questionPools.medium.slice(0, 7));
    selectedQuestions = selectedQuestions.concat(questionPools.difficult.slice(0, 7));

    // Track remaining questions in pools for replacements
    const remainingPools = {
        easy: questionPools.easy.slice(6),
        medium: questionPools.medium.slice(7),
        difficult: questionPools.difficult.slice(7)
    };

    let current = 0;
    let score = 0;
    let questionsAnswered = 0; // Track only answered questions for progress
    const totalQuestions = 20;
    const pointsPerQuestion = 5;

    function getDifficultyLabel(difficultyArr) {
        if (!difficultyArr || !difficultyArr.length) return '';
        let levels = ['Easy', 'Medium', 'Difficult'];
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
    }

    function getReplacementQuestion(difficulty) {
        const pool = remainingPools[difficulty];
        if (pool && pool.length > 0) {
            return pool.shift(); // Remove and return first question from pool
        }
        return null;
    }

    function showQuestion(idx) {
        if (!selectedQuestions[idx]) {
            document.getElementById('js-question-area').innerHTML = `<h2 class="completed">All questions complete!</h2><p class="final-score">Your final score: <strong>${score}</strong></p>`;
            return;
        }

        const q = selectedQuestions[idx].question.trim();
        const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(q);
        let difficulty = getDifficultyLabel(selectedQuestions[idx].difficulty);

        document.getElementById('js-question-area').innerHTML = `
            <div class="question-header">
                <h2 class="question-title">Question ${current + 1} of ${totalQuestions}</h2>
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
                let userAnswer = document.getElementById('userAnswer').value.trim();
                let correctAnswer = selectedQuestions[idx].answer.trim();
                let explanation = selectedQuestions[idx].explanation ? selectedQuestions[idx].explanation.trim() : '';
                
                let feedback = '';
                let isCorrect = userAnswer.toLowerCase() === correctAnswer.toLowerCase();
                
                if (isCorrect) {
                    score += pointsPerQuestion;
                    questionsAnswered++;
                    feedback = '<p style="color:#00A651;font-weight:600;font-size: 2rem;"><i class="fas fa-check"></i> Correct! +' + pointsPerQuestion + ' points.</p>';
                    
                    document.querySelector('#feedbackArea .feedback-content').innerHTML = feedback;
                    document.getElementById('feedbackArea').style.display = 'block';
                    
                    setTimeout(function() {
                        current++;
                        updateProgress();
                        showQuestion(current);
                    }, 3000);
                } else {
                    score -= 5; // Deduct 5 points for incorrect answer
                    feedback = '<p style="color:#e53935;font-weight:600;font-size: 2rem;"><i class="fas fa-times"></i> Incorrect. -5 points.</p><br>';
                    feedback += '<div style="color: black;margin-top:2px;"><strong>Correct Answer:</strong> ' + correctAnswer + '</div>';
                    if (explanation) {
                        feedback += '<div style="border:2px solid goldenrod;font-size:1.4rem;margin-top:8px;padding: 12px 24px;color: black;border-radius:8px;"><strong><i class="fas fa-info-circle"></i> Explanation:</strong> ' + explanation + '</div>';
                    }
                    feedback += '<button id="nextQuestionBtn" class="btn btn-next-question" style="margin-top:18px;font-size:1.25rem;padding:8px 20px;background:#034CA8;color:#fff;border:none;border-radius:6px;cursor:pointer;">Next Question >></button>';
                    
                    var submitBtn = document.querySelector('.btn-submit');
                    var skipBtn = document.querySelector('.btn-skip');
                    if (submitBtn) submitBtn.disabled = true;
                    if (skipBtn) skipBtn.disabled = true;
                    
                    document.querySelector('#feedbackArea .feedback-content').innerHTML = feedback;
                    document.getElementById('feedbackArea').style.display = 'block';
                    
                    // Update score display immediately after deduction
                    updateProgress();
                    
                    var nextBtn = document.getElementById('nextQuestionBtn');
                    if (nextBtn) {
                        nextBtn.onclick = function() {
                            let wrong = selectedQuestions.splice(current, 1)[0];
                            selectedQuestions.push(wrong);
                            updateProgress();
                            showQuestion(current);
                        };
                    }
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
                }
                
                // Don't increment questionsAnswered or score
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
})();
</script>

<?php get_footer(); ?>