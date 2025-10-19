    <h3 class="nav-title">Science Grade Levels</h3>
    <ul class="edu-nav-list">
        <!--
        Need to do a Foreach loop here to generate the list of subjects from the database
        Each subject should have a data-subject attribute to identify it. This will pulled from the Grade Level Table/Field.
        -->
        <?php
        $english_grades = get_terms(array(
            'taxonomy'   => 'science_grade',
            'hide_empty' => false,
            'parent'     => 0, // Only top-level grades
        ));

        foreach ($english_grades as $english_grade) {
            ?>
            <li class="nav-item" data-subject="<?php echo esc_attr($science_grade->slug); ?>" data-term-id="<?php echo esc_attr($science_grade->term_id); ?>" data-show-domains="true">
                <a href="#" class="nav-link" >
                    <?php if (!empty($science_grade->description)) : ?>
                        <span class="nav-desc"><?php echo esc_html($science_grade->description); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php
        }
        ?>
    </ul>
</nav>