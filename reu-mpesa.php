<?php

/*
Plugin Name: Reu Mpesa
Plugin URI: https://tsobu.co.ke/mpesa
Description: MPESA Payment plugin for wordpress
Version: 1.0
Author: Sammy Barasa
Author URI: https://tsobu.co.ke
License: GPL2
*/

defined('ABSPATH') or die('No script kiddies please!');

require_once 'vendor/autoload.php';

class MyGithub extends \Github\Client
{
}

;

//create shortcodes
function github_issues_func($atts)
{
    $gh = new MyGithub();

    // Make the API call to get issues, passing in the GitHub owner and repository
    $issues = $gh->api('issue')->all('TransitScreen', 'wp-github-pipeline');

    // Handle the case when there are no issues
    if (empty($issues)) {
        return "<strong>" . __("No issues to show", 'githup-api') . "</strong>";
    }

    // We're going to return a string. First, we open a list.
    $return = "<ul>";
    // Loop over the returned issues
    foreach ($issues as $issue) {

        // Add a list item for each issue to the string
        // (Feel free to get fancier here)
        // Maybe make each one a link to the issue issuing $issue['url] )
        $return .= "<li>{$issue['title']}</li>";

    }
    // Don't forget to close the list
    $return .= "</ul>";

    return $return;
}

add_shortcode("github_issues", "github_issues_func");