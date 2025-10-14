    <h3 class="nav-title">Math Grade Levels</h3>
    <ul class="edu-nav-list">
        <!--
        Need to do a Foreach loop here to generate the list of subjects from the database
        Each subject should have a data-subject attribute to identify it. This will pulled from the Grade Level Table/Field.
        -->
        <?php
        $grades = get_terms(array(
            'taxonomy'   => 'math_grade',
            'hide_empty' => false,
            'parent'     => 0, // Only top-level grades
        ));

        // Separate Algebra from other grades
        $algebra = [];
        $other_grades = [];

        foreach ($grades as $grade) {
            if (strtolower($grade->name) === 'algebra') {
                $algebra[] = $grade;
            } else {
                $other_grades[] = $grade;
            }
        }

        // Merge so Algebra is last
        $sorted_grades = array_merge($other_grades, $algebra);

        foreach ($sorted_grades as $grade) {
            ?>
            <li class="nav-item" data-subject="<?php echo esc_attr($grade->slug); ?>" data-term-id="<?php echo esc_attr($grade->term_id); ?>" data-show-domains="true">
                <a href="#" class="nav-link" >
                    <?php if (!empty($grade->description)) : ?>
                        <span class="nav-desc"><?php echo esc_html($grade->description); ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php
        }
        ?>
    </ul>
</nav>