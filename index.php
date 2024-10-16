<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/client.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

?><!doctype html>
<html>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Vote to Note Ratio Admin Panel</title>
		<style>
			label {
				white-space: nowrap;
			}
		</style>
	</head>
	<body>
		<h1>Vote to Note Ratio Admin Panel</h1>
<?php if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$r = $tumblr->reblogPost('vote-to-note-ratio', $_REQUEST['id'], $_REQUEST['reblog_key'], [
		'tags' => implode(',', $_REQUEST['tags']) . "," . $_REQUEST['extratags'],
		'body' => '<p>' . implode('</p><p>', $_REQUEST['lines']) . '</p>',
		'comment' => '<p>' . implode('</p><p>', $_REQUEST['lines']) . '</p>',
		'state' => 'queue',
		'type' => 'text',
	]);
?><p>
	<a href="https://www.tumblr.com/vote-to-note-ratio/<?=htmlspecialchars($r->id_string)?>">Success!</a>
	<form method="get"><button type="submit">Home</button></form>
</p>
<?php } else if (!empty($_REQUEST['post_url']) && preg_match('%\d+%', $_REQUEST['id'])) {
	$host = parse_url($_REQUEST['post_url'], PHP_URL_HOST);
	if ($host == 'www.tumblr.com') {
		$blog = current(array_filter(explode('/', parse_url($_REQUEST['post_url'], PHP_URL_PATH))));
	} else {
		$blog = $host;
	}
	$r = $tumblr->getBlogPosts($blog, [ 'id' => $_REQUEST['id'], 'npf' => 'true' ]);
	$post = $r->posts[0];
	$polls = [];
	foreach ($post->trail as $p) {
		foreach ($p->content as $block) {
			if ($block->type != 'poll') continue;
			$block->author = $p->blog->name;
			$polls[] = $block;
		}
	}
	foreach ($post->content as $block) {
		if ($block->type != 'poll') continue;
		$block->author = $post->blog->name;
		$polls[] = $block;
	}
	foreach ($polls as $poll) {
		$r = $tumblr->getRequest("v2/polls/$blog/{$_REQUEST['id']}/{$poll->client_id}/results", null, true);
		$total = 0;
		foreach ($poll->answers as $answer) {
			$answer->votes = $r->results->{$answer->client_id};
			$answer->chosen = in_array($answer->client_id, $r->user_votes);
			$total += $answer->votes;
		}
		$poll->vote_count = $total;
	}
	$supermajority = false;
	$majority = false;
	$closed = false;
	$ongoing = false;
	$multiples = [50 => false, 20 => false, 10 => false, 5 => false, 2 => false];
	$divisors = [1 => false, 2 => false, 5 => false, 10 => false, 20 => false, 50 => false];
	foreach ($polls as $poll) {
		foreach ($poll->answers as $answer) {
			if ($answer->votes >= $poll->vote_count * 2 / 3) {
				$supermajority = true;
			}
			if ($answer->votes > $poll->vote_count / 2) {
				$majority = true;
			}
		}
		if ((new DateTimeImmutable()) > (new DateTimeImmutable($poll->created_at))->add(new DateInterval('PT' . $poll->settings->expire_after . 'S'))) {
			$closed = true;
		} else {
			$ongoing = true;
		}
		foreach ($multiples as $mult => $yes) {
			if ($poll->vote_count / $post->note_count > $mult) {
				$multiples[$mult] = true;
			}
		}
		foreach ($divisors as $div => $yes) {
			if ($post->note_count / $poll->vote_count > $div) {
				$divisors[$div] = true;
			}
		}
		$poll->statement = 'Vote to note ratio = '
			. number_format($poll->vote_count)
			. ':'
			. number_format($post->note_count)
			. ' ≈ ';
		if ($poll->vote_count >= $post->note_count) {
			$poll->statement .= number_format(ceil($poll->vote_count / $post->note_count * 100) / 100, 2) . 'x as many votes as notes';
		} else {
			$poll->statement .= number_format(ceil($post->note_count / $poll->vote_count * 100) / 100, 2) . 'x as many NOTES as VOTES';
		}
	}
	$tags = [
		'vote to note ratio' => true,
		'as of time of writing' => true,
	];
	foreach ($multiples as $mult => $yes) {
		if (!$yes) continue;
		$tags['>' . $mult . 'x'] = true;
	}
	foreach ($divisors as $div => $yes) {
		if (!$yes) continue;
		if ($div == 1) $tags['<1x'] = true;
		else $tags['<1/' . $div . 'x'] = true;
	}
	$tags = array_merge($tags, [
		'“other” supermajority' => $supermajority ? null : false,
		'“other” majority' => $majority ? null : false,
		'“other” plurality' => null,
		'ongoing poll' => $ongoing,
		'closed poll' => $closed,
		'poll' => true,
		'polls' => true,
		'tumblr poll' => true,
		'tumblr polls' => true,
		'poll blog' => null,
	])
?>
	<p><form method="get"><button type="submit">Home</button></form></p>
<form method="post">
<?php foreach ($polls as $poll) { ?>
	<fieldset>
		<legend><?=htmlspecialchars($poll->author)?>: <?=htmlspecialchars($poll->question)?></legend>
		<ul><?php foreach ($poll->answers as $answer) { ?>
			<li <?=$answer->chosen ? ' style="text-decoration: underline"' : ''?>>
				<?=htmlspecialchars($answer->answer_text)?> - <?=number_format($answer->votes * 100 / $poll->vote_count, 1)?>%
			</li>
		<?php } ?></ul>
		<input type="hidden" name="lines[]" value="<?=$poll->statement?>" />
		<p><?=$poll->statement?></p>
	</fieldset>
<?php } ?>
	<p>
		<?php foreach ($tags as $tag => $yes) { ?>
		<label>
			<input type="checkbox" name="tags[]" value="<?=htmlspecialchars($tag)?>"<?=is_null($yes) ? '' : ' disabled'?><?=$yes ? ' checked' : ''?> />
			# <?=htmlspecialchars($tag)?>
		</label>
		<?php if ($yes === true) { ?>
		<input type="hidden" name="tags[]" value="<?=htmlspecialchars($tag)?>" />
		<?php } ?>
		<?php } ?>
		<label># <input type="text" name="extratags" /></label>
		<button type="submit">Queue</button>
	</p>
	<input type="hidden" name="id" value="<?=htmlspecialchars($_REQUEST['id'])?>" />
	<input type="hidden" name="reblog_key" value="<?=htmlspecialchars($post->reblog_key)?>" />
</form>
<?php } else { ?>
		<form method="get">
			<p>
				Enter post URL:
				<input type="text" name="post_url" id="post_uri" />
				<span id="ids">
				</span>
				<button type="submit">Fetch</button>
			</p>
		</form>
		<script>
			document.getElementById('post_uri').addEventListener('change', e => {
				document.getElementById('ids').innerHTML = e.target.value.match(/\d+/g).map(
					(s, i) => `<label><input type="radio" name="id" value="${s}" ${i == 0 ? 'checked' : ''}/> ${s}</label>`
				).join('\n');
			})
		</script>
<?php } ?>
	</body>
</html>
