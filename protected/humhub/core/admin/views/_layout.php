<div class="container">
    <div class="row">
        <div class="col-md-3">
            <?= \humhub\core\admin\widgets\AdminMenu::widget(); ?>
        </div>
        <div class="col-md-9">
            <?php echo $content; ?>
        </div>
    </div>
</div>