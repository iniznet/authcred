<<?= $tag ?> class="<?= $class ?>">
	<?php while ($posts->have_posts()): $posts->the_post(); ?>
		<li>
			<a href="<?php the_permalink(); ?>">
				<?php the_title(); ?>
			</a>
		</li>
	<?php endwhile; wp_reset_postdata(); ?>
</<?= $tag ?>>