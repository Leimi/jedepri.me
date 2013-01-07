<?php
	/**
	 * ce code est amicalement sponsorisé par la méthode rache
	 */
	define('PROD', (!empty($_SERVER['SERVER_NAME']) && strpos($_SERVER['SERVER_NAME'], APP_SERVER) !== false));
	if (PROD)
		error_reporting(0);
	
	require 'config/app.php';
	require 'config/database.php';
	require 'vendor/autoload.php';
	require 'lib/JedeprimeItemDatabase.php';
	require 'lib/helpers.php';

	$pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME."", DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
	$db = new JedeprimeItemDatabase($pdo, SOURCES_FILE, intval(!PROD));
	$app = new \Slim\Slim(array(
		'templates.path' => './views',
		'debug' => intval(!PROD)
	));
	define('HOST', $app->request()->getUrl());

	$app->hook('slim.before.dispatch', function() use ($app) {
		$app->view()->appendData(array(
			'app' => $app
		));
	});

	$app->get('/', function() use ($app, $db) {
		$nextItemSlug = $db->getRandomItemSlug();
		$app->render('question.php', array('simpleText' => true, 'nextItemSlug' => $nextItemSlug));
	})->name('home');

	$app->get('/jarretededeprimer/:slug', function($slug) use ($app, $db) {
		//on vérifie le cookie contenant les ids d'images déjà vues (c'est un tableau d'ids)
		$seenImgsCookie = $app->getCookie('seen_item_ids');
		if ($seenImgsCookie) {
			$seenImgIds = array_filter(explode(';', htmlspecialchars($seenImgsCookie)));
			$idsOk = true;
			foreach ($seenImgIds as $id) { if (!is_numeric($id)) { $idsOk = false; break; } }
		}
		if (empty($seenImgIds) || empty($idsOk)) $seenImgIds = array();
		$item = $db->getItemBySlug($slug);
		if (!in_array($item['id'], $seenImgIds))
			$seenImgIds[]= $item['id'];
		$nextItemSlug = $db->getRandomItemSlug($seenImgIds);
		
		$app->setCookie('seen_item_ids', implode(';', $seenImgIds), time()+60*60*24*3);

		$title = ($item['content-type'] == 'img-url' ? "Une image" : "Un truc").' qui fait arrêter de déprimer - Je déprime';
		$app->render('question.php', array(
			'item' => $item, 
			'nextItemSlug' => $nextItemSlug,
			'twitterCard' => twitterCard($item),
			'title' => $title
		));
	})->name('question');

	$app->get('/jarretededeprimer/', function() use ($app, $db) {
		$app->redirect('/jarretededeprimer/'.$db->getRandomItemSlug());
	})->name('question-empty');

	$app->get('/cayestjedeprimeplus/:slug', function($slug) use ($app, $db) {
		$title = "J'ai arrêté ma dépression grâce à ".($item['content-type'] == 'img-url' ? "cette image" : "ce truc").' ! - Je déprime';
		$app->render('partage.php', array(
			'item' => $db->getItemBySlug($slug),
			'twitterCard' => twitterCard($item),
			'title' => $title
		));
	})->name('partage');

	$app->get('/cayestjedeprimeplus/', function() use ($app, $db) {
		$app->render('partage.php', array(
			"title" => "J'ai arrêté ma depression sur jedepri.me ! - Je déprime"
		));
	})->name('partage-empty');
	
	$app->get('/updateImages', function() use($db) {
		$db->fillDB();
	})->name('updateImages');

	$app->get('/random', function() use ($app, $db) {
		echo HOST.'/jarretededeprimer/'.$db->getRandomItemSlug();
	});

	$app->get('/:slug', function($slug) use($app) {
		$app->redirect('/jarretededeprimer/'.$slug, 301);
	});

	$app->run();
?>