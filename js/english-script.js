/**
 * Educational Learning Template JavaScript
 * Handles form submission, navigation, and interactive elements
 */

(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        
        // Cache DOM elements
        const $answerForm = $('#learningAnswerForm');
        const $feedbackArea = $('#feedbackArea');
        const $navLinks = $('.edu-nav-list .nav-link');
        const $skillLevels = $('.skill-indicator .level');
        const $hintToggle = $('.hint-toggle');
        const $userAnswer = $('#userAnswer');

        /**
         * Handle grade level navigation clicks
         */
        $('.nav-item').on('click', function(e) {
            e.preventDefault();
            var termId = $(this).attr('data-term-id');
            var $contentArea = $('.learning-module-content');
            var showDomains = $(this).attr('data-show-domains'); // Add this attribute to control behavior
            
            // Debug logging
            console.log('Nav item clicked:', {
                termId: termId,
                showDomains: showDomains,
                element: this
            });
            
            $contentArea.html('Loading...');

            // Choose which AJAX action to call
            var action = showDomains === 'true' ? 'get_english_domains_with_grades' : 'get_english_grade_children';
            var data = {
                action: action,
                parent_id: termId  // <-- Always use parent_id for both actions
            };

            console.log('AJAX call:', {
                url: englishSkillAjax.ajax_url,
                data: data
            });

            $.post(englishSkillAjax.ajax_url, data, function(response) {
                console.log('AJAX response received:', response);
                $contentArea.html(response);
            }).fail(function(xhr, status, error) {
                console.error('AJAX error:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                $contentArea.html('Error loading content. Please try again. Check console for details.');
            });        
        });

        /**
         * Handle answer form submission
         */
        $answerForm.on('submit', function(e) {
            e.preventDefault();
            
            const userAnswer = $userAnswer.val().trim();
            
            // Validate answer
            if (!userAnswer) {
                showFeedback('Please enter an answer before submitting.', 'error');
                return;
            }
            
            // Check answer (In production, this would be done server-side)
            checkAnswer(userAnswer);
        });

        /**
         * Check user's answer
         */
        /**
 * Check user's answer
 */
function checkAnswer(userAnswer) {
    // Show loading state
    const $submitBtn = $('.btn-submit');
    const originalText = $submitBtn.html();
    $submitBtn.html('<span class="spinner">⌛</span> Checking...').prop('disabled', true);
    
    // Debug logging
    console.log('=== CHECKING ANSWER ===');
    console.log('User answer:', userAnswer);
    console.log('Current question:', currentQuestion);
    
    // Simulate server request
    setTimeout(function() {
        // Check if currentQuestion exists
        if (typeof currentQuestion === 'undefined' || !currentQuestion) {
            console.error('ERROR: currentQuestion is not defined!');
            showFeedback('Error: Question data not available. Please refresh the page.', 'error');
            $submitBtn.html(originalText).prop('disabled', false);
            return;
        }
        
        console.log('Expected answer:', currentQuestion.correctAnswer);
        
        // In production, make AJAX call to server here
        const isCorrect = userAnswer.toLowerCase() === currentQuestion.correctAnswer.toLowerCase();
        
        console.log('Is correct?', isCorrect);
        console.log('Comparison:', userAnswer.toLowerCase(), '===', currentQuestion.correctAnswer.toLowerCase());
        
        if (isCorrect) {
            console.log('>>> CORRECT ANSWER - Will auto-advance in 3 seconds');
            // CORRECT ANSWER - show success feedback and auto-advance
            showFeedback('🎉 Correct! ' + currentQuestion.explanation, 'success');
            updateProgress(10); // Add 10% to progress
            
            // Auto-advance to next question after 3 seconds
            setTimeout(function() {
                console.log('>>> Auto-advancing to next question');
                loadNextQuestion();
            }, 3000);
            
        } else {
            console.log('>>> INCORRECT ANSWER - Will NOT auto-advance');
            // INCORRECT ANSWER - show error feedback but DO NOT auto-advance
            showFeedback('❌ Not quite right. Try again!', 'error');
            // No loadNextQuestion() call here - user must try again or manually skip
        }
        
        // Reset button
        $submitBtn.html(originalText).prop('disabled', false);
        
        // Log attempt (would be sent to server)
        logAttempt(userAnswer, isCorrect);
        
    }, 1000);
}

        /**
         * Show feedback to user
         */
        function showFeedback(message, type) {
            $feedbackArea
                .removeClass('success error')
                .addClass(type)
                .find('.feedback-content')
                .html(message);
            
            $feedbackArea.slideDown(500);
            
            // Auto-hide error messages after 5 seconds
            // if (type === 'error') {
            //     setTimeout(function() {
            //         $feedbackArea.slideUp(500);
            //     }, 50000);
            // }
        }

        /**
         * Load next question
         */
        function loadNextQuestion() {
            // Show loading state
            $('.question-text').html('<span class="spinner">⌛</span> Loading next question...');
            
            // In production, fetch from server
            setTimeout(function() {
                // Update UI
                $('.question-text').html(currentQuestion.question);
                $userAnswer.val('');
                $feedbackArea.slideUp(300);
                $('.hint-content').hide();
                
            }, 1000);
        }

        /**
         * Handle navigation clicks
         */
        $navLinks.on('click', function(e) {
            e.preventDefault();
            
            // Update active state
            $navLinks.removeClass('active');
            $(this).addClass('active');
            
            // Get selected subject
            const subject = $(this).parent().data('subject');
            
            // Load questions for this subject
            loadSubjectQuestions(subject);
        });

        /**
         * Load questions for specific subject
         */
        function loadSubjectQuestions(subject) {
        console.log('Loading questions for:', subject);
        
        // Define content area
        var $contentArea = $('.learning-module-content');
        
        // Show loading state
        $contentArea.html('Loading...');
        
        $.ajax({
            url: englishSkillAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_english_domains_with_grades',
                parent_id: subject,
                nonce: englishSkillAjax.nonce
            },
            success: function(response) {
                $contentArea.html(response);
            },
            error: function() {
                showFeedback('Error loading questions. Please try again.', 'error');
            },
            complete: function() {
                $('.learning-module').css('opacity', '1');
            }
        });
    }

        /**
         * Handle skill level selection
         */
        $skillLevels.on('click', function() {
            // Update active state
            $skillLevels.removeClass('active');
            $(this).addClass('active');
            
            // Get selected level
            const level = $(this).data('level');
            currentQuestion.level = level;
            
            // Filter questions by level (in production)
            filterQuestionsByLevel(level);
        });

        /**
         * Toggle hint display
         */
        $hintToggle.on('click', function() {
            const $hintContent = $('.hint-content');
            
            if ($hintContent.is(':visible')) {
                $hintContent.slideUp(300);
                $(this).html('<span class="hint-icon">💡</span> Need a hint?');
            } else {
                $hintContent.slideDown(300);
                $(this).html('<span class="hint-icon">💡</span> Hide hint');
                
                // Track hint usage
                trackHintUsage(currentQuestion.id);
            }
        });

        /**
         * Handle Skip Question
         */
        $('.btn-skip').on('click', function() {
            if (confirm('Are you sure you want to skip this question?')) {
                logAttempt('skipped', false);
                loadNextQuestion();
            }
        });

        /**
         * Handle Learning Tools
         */
        $('#calculatorBtn').on('click', function() {
            openCalculator();
        });

        $('#notesBtn').on('click', function() {
            openNotesPanel();
        });

        $('#resourcesBtn').on('click', function() {
            openResourcesPanel();
        });

        /**
         * Open calculator modal
         */
        function openCalculator() {
            // Create calculator modal if it doesn't exist
            if (!$('#calculatorModal').length) {
                const calculatorHTML = `
                    <div id="calculatorModal" class="edu-modal">
                        <div class="modal-content">
                            <span class="close-modal">&times;</span>
                            <h3>Calculator</h3>
                            <div class="calculator-body">
                                <input type="text" id="calcDisplay" readonly>
                                <div class="calc-buttons">
                                    <!-- Calculator buttons would go here -->
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('body').append(calculatorHTML);
            }
            
            $('#calculatorModal').fadeIn(300);
        }

        /**
         * Open notes panel
         */
        function openNotesPanel() {
            // Create notes panel if it doesn't exist
            if (!$('#notesPanel').length) {
                const notesHTML = `
                    <div id="notesPanel" class="edu-side-panel">
                        <div class="panel-header">
                            <h3>My Notes</h3>
                            <span class="close-panel">&times;</span>
                        </div>
                        <div class="panel-body">
                            <textarea id="userNotes" placeholder="Take notes here..."></textarea>
                            <button class="save-notes-btn">Save Notes</button>
                        </div>
                    </div>
                `;
                $('body').append(notesHTML);
            }
            
            $('#notesPanel').addClass('active');
        }

        /**
         * Open resources panel
         */
        function openResourcesPanel() {
            console.log('Opening resources panel...');
            // Implementation for resources panel
        }

        /**
         * Update progress bar
         */

        /**
         * Log user attempt (for analytics)
         */
        function logAttempt(answer, isCorrect) {
            // In production, send to server
            const attemptData = {
                questionId: currentQuestion.id,
                subject: currentQuestion.subject,
                level: currentQuestion.level,
                answer: answer,
                isCorrect: isCorrect,
                timestamp: new Date().toISOString()
            };
            
            console.log('Logging attempt:', attemptData);
            
            // Send to server via AJAX in production
        }

        /**
         * Track hint usage
         */
        function trackHintUsage(questionId) {
            // Analytics tracking
            console.log('Hint used for question:', questionId);
        }

        /**
         * Filter questions by level
         */
        function filterQuestionsByLevel(level) {
            console.log('Filtering questions by level:', level);
            // Implementation for filtering
        }

        /**
         * Update question display
         */
        function updateQuestionDisplay(data) {
            // Update the question content with new data
            $('.question-text').html(data.question);
            // Update other elements as needed
        }

        /**
         * Handle modal/panel close buttons
         */
        $(document).on('click', '.close-modal, .close-panel', function() {
            $(this).closest('.edu-modal, .edu-side-panel').fadeOut(300).removeClass('active');
        });

        /**
         * Handle keyboard shortcuts
         */
        $(document).on('keydown', function(e) {
            // Enter key to submit when input is focused
            if (e.key === 'Enter' && $userAnswer.is(':focus')) {
                $answerForm.submit();
            }
            
            // Escape key to close modals
            if (e.key === 'Escape') {
                $('.edu-modal:visible').fadeOut(300);
                $('.edu-side-panel.active').removeClass('active');
            }
        });

        /**
         * Auto-save user progress
         */
        setInterval(function() {
            saveUserProgress();
        }, 30000); // Save every 30 seconds

        /**
         * Save user progress
         */
        // function saveUserProgress() {
        //     const progressData = {
        //         currentQuestion: currentQuestion.id,
        //         progressPercent: $('.progress-text').text(),
        //         timestamp: new Date().toISOString()
        //     };
            
        //     // In production, save to server
        //     console.log('Auto-saving progress:', progressData);
        // }

        /**
         * Initialize tooltips if needed
         */
        $('[data-tooltip]').each(function() {
            $(this).hover(
                function() {
                    const tooltip = $('<div class="tooltip">' + $(this).data('tooltip') + '</div>');
                    $('body').append(tooltip);
                    const pos = $(this).offset();
                    tooltip.css({
                        top: pos.top - tooltip.height() - 10,
                        left: pos.left + ($(this).width() / 2) - (tooltip.width() / 2)
                    }).fadeIn(200);
                },
                function() {
                    $('.tooltip').remove();
                }
            );
        });

    }); // End of document ready

})(jQuery);