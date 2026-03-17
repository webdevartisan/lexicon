<!DOCTYPE html>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>
	<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title>{% yield title %}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
      // per-page description if you pass $meta['description']
      $desc = htmlspecialchars($meta['description'] ?? ($user['blog_name'] ?? 'Blog'), ENT_QUOTES);
	?>
    <meta name="description" content="<?= $desc ?>">
	<meta name="keywords" content="blog, posts">
	<meta name="author" content="<?= htmlspecialchars($user['display_name_cached'] ?? $user['username'] ?? 'Author', ENT_QUOTES) ?>">

  	<!-- Facebook and Twitter integration -->
	<meta property="og:title" content=""/>
	<meta property="og:image" content=""/>
	<meta property="og:url" content=""/>
	<meta property="og:site_name" content=""/>
	<meta property="og:description" content=""/>
	<meta name="twitter:title" content="" />
	<meta name="twitter:image" content="" />
	<meta name="twitter:url" content="" />
	<meta name="twitter:card" content="" />

	<?php $logo = $settings['logo_path'] ?? null;
	$fav = $settings['favicon_path'] ?? null; ?>
	<!-- Place favicon.ico and apple-touch-icon.png in the root directory -->
	<link rel="shortcut icon" href="<?= $fav ?: $asset('images/favicon.png') ?>">

	<link rel="preload" href="<?= $asset('fonts/roboto-slab/RobotoSlab-VariableFont_wght.woff2') ?>" as="font" type="font/woff2" crossorigin>

	
    <link rel="stylesheet" href="<?= $asset('css/animate.css') ?>">
    <link rel="stylesheet" href="<?= $asset('css/icomoon.css') ?>">
    <link rel="stylesheet" href="<?= $asset('css/bootstrap.css') ?>">
    <link rel="stylesheet" href="<?= $asset('css/style.css') ?>">


	</head>
	<body class="theme-<?= htmlspecialchars($theme ?? 'default', ENT_QUOTES) ?>">

	<?php if (!empty($flashes ?? [])) { ?>
	<div class="container mt-3">
		<?php foreach ($flashes as $type => $messages) { ?>
		<?php
	        // Map logical types to Bootstrap classes
	        $class = match ($type) {
	            'success' => 'alert-success',
	            'error' => 'alert-danger',
	            'warning' => 'alert-warning',
	            default => 'alert-info',
	        };
		    ?>
		<?php foreach ($messages as $message) { ?>
			<div class="alert <?= $class ?>" role="alert">
			<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
			</div>
		<?php } ?>
		<?php } ?>
	</div>
	<?php } ?>

	<div class="box-wrap">
		<header role="banner" id="fh5co-header">
			<div class="container">
				<nav class="navbar navbar-default">
					<div class="row">
						<div class="col-md-3">
							<div class="fh5co-navbar-brand">
								<a class="fh5co-logo" href="<?= '/blog/'.urlencode($blog['blog_slug']) ?>">
								<img src="<?= $logo ?: $asset('images/brand-nav.png') ?>"
									alt="<?= htmlspecialchars($user['blog_name'] ?? 'Blog', ENT_QUOTES) ?>"
									style="max-height:56px; height:auto; width:auto; object-fit:contain;">
								</a>
							</div>
						</div>
						<div class="col-md-6">
							<ul class="nav text-center">
								<li><a href="<?= '/blog/'.urlencode($blog['blog_slug']) ?>"><span>Home</span></a></li>
								<li class="active"><a href="inside.html">About</a></li>
								<li><a href="contact.html">Contact</a></li>
							</ul>
						</div>
						<div class="col-md-3">
							<ul class="social">
								<li><a href="#"><i class="icon-twitter"></i></a></li>
								<li><a href="#"><i class="icon-dribbble"></i></a></li>
								<li><a href="#"><i class="icon-instagram"></i></a></li>
							</ul>
						</div>
					</div>
				</nav>
		  </div>
		</header>
		<!-- END: header -->
		
		{% yield content %}

		<footer id="footer" role="contentinfo">
			<div class="container">
				<div class="row">
					<div class="col-md-12 text-center">
	              	<div class="footer-widget border">
						<p class="pull-left"><small>&copy; <?= date('Y') ?> <?= htmlspecialchars($user['blog_name'] ?? 'Blog', ENT_QUOTES) ?>.</small></p>
						<p class="pull-right"><small>
							Published & hosted by <a href="/"><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'bloghub.example', ENT_QUOTES) ?></a>
						</small></p>
					</div>
					</div>
				</div>
			</div>
		</footer>
	</div>
	<!-- END: box-wrap -->
	
	<!-- jQuery -->
	<script src="<?= $asset('js/jquery.min.js') ?>"></script>
	<!-- jQuery Easing -->
	<script src="<?= $asset('js/jquery.easing.1.3.js') ?>"></script>
	<!-- Bootstrap -->
	<script src="<?= $asset('js/bootstrap.min.js') ?>"></script>
	<!-- Waypoints -->
	<script src="<?= $asset('js/jquery.waypoints.min.js') ?>"></script>

	<!-- Main JS (Do not remove) -->
	<script src="<?= $asset('js/main.js') ?>"></script>

	</body>
</html>




