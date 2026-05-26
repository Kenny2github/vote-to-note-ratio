# Vote to Note Ratio Admin Panel
This is a small server-side web app that automates most of the work that goes into [my gimmick blog](https://tumblr.com/vote-to-note-ratio). See [Usage](#usage) for the non-automated parts.

## Installation
1. Clone the repo and `cd` into it.
2. `composer install`, using the same version of PHP to run Composer as is running on your server.
3. Get credentials from the [API Console](https://api.tumblr.com/console/calls/user/info) in PHP for use in the next step.
3. Create `client.php` next to `index.php` as follows:
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

// The necessary credentials
$consumer_key = ''; // your consumer key
$consumer_secret = ''; // your consumer secret
$token_key = ''; // your token
$token_secret = ''; // your token secret

// Authenticate via OAuth
$tumblr = new Tumblr\API\Client(
  $consumer_key,
  $consumer_secret,
  $token_key,
  $token_secret
);

unset($consumer_key);
unset($consumer_secret);
unset($token_key);
unset($token_secret);

if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
	http_response_code(403);
  die;
}
```

## Usage
Screenshots (on mobile, as of [commit `e4c19b7`](https://github.com/Kenny2github/vote-to-note-ratio/tree/e4c19b77d137b64c729e22bdb3ee21948a866ebf)) in, fittingly, [this Tumblr post](https://www.tumblr.com/vote-to-note-ratio/764470328838766592).
1. Navigate to web root ([canonical](https://vtnr.abyx.dev) - gated behind authentication for obvious reasons).
2. Enter post URL.
3. Choose actual post ID from list of digit strings.
4. Click "Fetch".
5. A summary of the polls in the post is displayed. Underlined options are ones you selected (followed by your profile picture in the Tumblr interface); bolded options are ones that won the poll (highlighted blue in the Tumblr interface).
6. Depending on whether the poll is binary (has two options):
    - Check the '# "other" {supermajority,majority,plurality}' tags if the option receiving the respective portion of the vote is, in your judgement, an "other" option. The supermajority and majority tags will only be checkable if an option received that much of the vote.
    - Check the '# "{yes,no}" {supermajority,majority}' tags if the binary poll is, in your judgement, a "yes/no" poll and the respective option won. The supermajority tags will only be checkable if the option won by that much of the vote.
7. Check the "# poll blog" tag if any of the polls come from a poll blog, in your judgement.
8. Enter an arbitrary tag for the blog admin's response to the poll. Additional tags can be entered by clicking the "Add tag" button. Commas will also be split into separate tags.
9. Click "Queue".
10. A success message is displayed which links to the newly queued post. Click "Home" to return to step 1.
