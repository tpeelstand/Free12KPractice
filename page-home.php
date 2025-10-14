<?php
 // Template Name: Custom Homepage
$homepage_image = get_field('homepage_image');
$homepage_lower_content = get_field('homepage_lower_content');
$hp_lower_image = get_field('hp_lower_image');

 ?>
<?php get_header(); ?>
<div class="hp-container container">
    <div class="row">
        <div class="hp-content col-md-12">
            <div class="col-md-7 col-sm-12">
                <?= the_content(); ?>
            </div>
            <div class="col-md-5 col-sm-12">
                <img src="<?= esc_url( $homepage_image['url'] ); ?>" alt="<?= esc_attr( $homepage_image['alt'] ); ?>" />
            </div>
        </div>

        <div class="hp-links col-md-12 col-sm-12">
            <!-- 
                TEMP STYLING WHILE GETTING OTHER SUBJECTS ONLINE. 
                REMOVE ONCE ALL SUBJECTS ARE ACTIVE 
            -->
            <style>
            .btn-home.disabled {
                pointer-events: none;
                opacity: 0.6;
                position: relative;
                color: #aaa !important;
            }
            .btn-home .coming-soon {
                position: absolute;
                top: 12px;
                left: -6px;
                background: #000;
                border-radius: 0 8px 8px 0;
                color: #fff;
                padding: 14px 28px;
                font-size: 2rem;
                font-weight: bold;
                z-index: 2;
                pointer-events: none;
                box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            }
            </style>
            <!-- END -->
            <div class="btn-home-container col-sm-12">
                <a href="<?php echo esc_url( home_url( '/math' ) ); ?>" class="btn-home btn-math" tabindex="-1" style="position:relative;">
                    <i class="fas fa-divide"></i>&nbsp; Math
                </a>
                <a href="<?php echo esc_url( home_url( '/english' ) ); ?>" class="btn-home btn-english" tabindex="-1" style="position:relative;">
                    <!-- <span class="coming-soon">Coming Soon</span> -->
                    <i class="fas fa-book-open"></i>&nbsp; English
                </a>
            </div>
            <div class="btn-home-container col-sm-12">
                <a class="btn-home btn-science disabled" tabindex="-1" style="position:relative;">
                    <span class="coming-soon">Coming Soon</span>
                    <i class="fas fa-atom"></i>&nbsp; Science
                </a>
                <a class="btn-home btn-sstudies disabled" tabindex="-1" style="position:relative;">
                    <span class="coming-soon">Coming Soon</span>
                    <i class="fas fa-globe"></i>&nbsp; Social Studies
                </a>
            </div>
            <!-- End Button Links -->
        </div>

        <!-- Content Below Buttons -->
        <div class="hp-lower-content col-md-12">
            <div class="hp-lower-content__image col-md-5 col-sm-12">
                <img src="<?= esc_url( $hp_lower_image['url'] ); ?>" alt="<?= esc_attr( $hp_lower_image['alt'] ); ?>" />
            </div>
            <div class="hp-lower-content__text col-md-7 col-sm-12">
                <?= $homepage_lower_content ?>
            </div>
        </div>

    </div>
</div>

<?php get_footer();?>