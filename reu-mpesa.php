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
function github_issues_func($atts, $gh = null)
{
    $gh = ($gh) ? $gh : new MyGithub();

    $githubOrg = get_option("gh_org");
    $repoName = get_option("gh_repo");

    // Make the API call to get issues, passing in the GitHub owner and repository
//    $issues = $gh->api('issue')->all('TransitScreen', 'wp-github-pipeline');

    $issues = $gh->api("issue")->all($githubOrg, $repoName);

    // Handle the case when there are no issues
    if (empty($issues)) {
        return "<strong>" . __("No issues to show", 'reu-mpesa') . "</strong>";
    }

    // We're going to return a string. First, we open a list.
    $return = "<ol start='1'>";
    // Loop over the returned issues
    foreach ($issues as $issue) {

        // Add a list item for each issue to the string
        // (Feel free to get fancier here)
        // Maybe make each one a link to the issue issuing $issue['url] )
        $return .= "<li>{$issue['title']}</li>";

    }
    // Don't forget to close the list
    $return .= "</ol>";

    return $return;
}

add_shortcode("github_issues", "github_issues_func");

//Register settings menu
add_action("admin_menu", "gh_plugin_menu_func");
function gh_plugin_menu_func()
{
    add_submenu_page("options-general.php",
        "GitHub",
        "GitHub",
        "manage_options",
        "github",
        "gh_plugin_options"
    );
}

//print markup for the page
function gh_plugin_options()
{
    if (!current_user_can("manage_options")) {
        wp_die(__("You do not have sufficient permissions to access this page."));
    }

    if (isset($_GET['status']) && $_GET['status'] == 'success') {
        ?>
        <div id="message" class="updated notice is-dismissible">
            <p><?php _e("Settings updated!", "reu-mpesa"); ?></p>
            <button type="button" class="notice-dismiss">
                <span class="screen-reader-text"><?php _e("Dismiss this notice.", "reu-mpesa"); ?></span>
            </button>
        </div>
        <?php
    }
    ?>


    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">

        <input type="hidden" name="action" value="update_github_settings"/>

        <h3><?php _e("GitHub Repository Info", "reu-mpesa"); ?></h3>
        <p>
            <label><?php _e("GitHub Organization:", "reu-mpesa"); ?></label>
            <input class="" type="text" name="gh_org" value="<?php echo get_option('gh_org'); ?>"/>
        </p>

        <p>
            <label><?php _e("GitHub repository (slug):", "reu-mpesa"); ?></label>
            <input class="" type="text" name="gh_repo" value="<?php echo get_option('gh_repo'); ?>"/>
        </p>

        <input class="button button-primary" type="submit" value="<?php _e("Save", "reu-mpesa"); ?>"/>

    </form>
    <?php
}

add_action('admin_post_update_github_settings', 'github_handle_save');

function github_handle_save()
{
// Get the options that were sent
    $org = (!empty($_POST["gh_org"])) ? $_POST["gh_org"] : NULL;
    $repo = (!empty($_POST["gh_repo"])) ? $_POST["gh_repo"] : NULL;

    // Validation would go here

    // Update the values
    update_option("gh_repo", $repo, TRUE);
    update_option("gh_org", $org, TRUE);

    // Redirect back to settings page
    // The ?page=github corresponds to the "slug"
    // set in the fourth parameter of add_submenu_page() above.
    $redirect_url = get_bloginfo("url") . "/wp-admin/options-general.php?page=github&status=success";
    header("Location: " . $redirect_url);
    exit;
}