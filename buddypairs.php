<?php
/*
 * Plugin Name: buddypairs
 * Text Domain: buddypairs
 * Domain Path: /languages
 * Plugin URI: https://github.com/Jon007/buddypairs/
 * Assets URI: https://github.com/Jon007/buddypairs/assets/
 * Author: Jonathan Moore
 * Author URI: https://jonmoblog.wordpress.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: BuddPress customizations for Schools with Students, Teachers and Buddy Pair groups
 * Contributors: jonathanmoorebcsorg
 * Version: 1.0.0
 * Stable Tag: 1.0.
 * Requires At Least: 4.7
 * Tested Up To: 4.8
 */
/* moved from bp-custom, a special file for custom code affecting the behaviour of BuddyPress
 * this allows these behaviour functions to persist when changing themes
 * 
 * General note:
 * General hooks available on BuddyPress functions using bbp_parse_args
 *      bbp_before_*function_name*_parse_args
 *      bbp_after_*function_name*_parse_args
 * all have generic 
 *    @param string|array $args Value to merge with $defaults
 * See *function_name* for details of applicable arguments
 * 
 * Main customizations covered in this file are:
 *  1. Editing enhancements: visual editor buttons, image upload
 *  2. Admin functions: eg enhancement to allow bulk addition of users to groups in user admin
 *  3. User Profile enhancements: additional fields display and Search integration 
 *  4. permissions handling: jmbp_can_manage, jmbp_is_teacher_of_student, jmbp_same_school
 *  5. Creation of New Groups customization/simplification of process
 *  ** off-cuts, alternative drafts
 */

/*
 *  1. Visual editor enhancements:
 *  - jmbp_enable_visual_editor         rich text for forums 
 *  - jmbp_re_enable_mce_full_screen    full screen editing
 *  - add image upload button to documents
 *  - optionally enable rich text for activity updates
 */


/* 
 * bbp_get_the_content - add arguments to enable tinymce editor for forums 
 * 
 * @param array     $args
 * 
 * return array     modified args
 * 
 */
function jmbp_enable_visual_editor($args = array())
{
    $args['tinymce'] = true;
    $args['teeny'] = false;
    $args['fullscreen'] = true;
    return $args;
}
add_filter('bbp_after_get_the_content_parse_args', 'jmbp_enable_visual_editor');

/* 
 * increase height of editor windows by default 
 */
function jmbp_increase_editor_size($initArray)
{
    $initArray['height'] = '350px';
    return $initArray;
}
add_filter('tiny_mce_before_init', 'jmbp_increase_editor_size');

/* 
 * enable fullscreen on forum tinymce editor 
 * 
 * @param array     $args
 * 
 * return array     modified args
 * 
 */
function jmbp_re_enable_mce_full_screen($plugins = array())
{
    $plugins[] = 'fullscreen';
    return $plugins;
}
add_filter('bbp_get_tiny_mce_plugins', 'jmbp_re_enable_mce_full_screen');

/* enable inline image upload for BuddyPress docs */
add_filter('bp_docs_wp_editor_args', 'hm_bbpiu_modify_editor', 9999);


/* 
 * enable tinyMCE editor for BuddyPress activity:  
 * this is not enabled as overlaps with discussion forum functionality, it should be clearer to
 * encourage use of the discussion and documents which have this feature
 * 
 * If needed, to enable, see https://buddypress.org/support/topic/add-tinymce-to-activity-post-form/
 * create customization in theme for /buddypress/activity/post-form.php (or BP Nouveau equivalent)
 * with:
 * 
 * <div id="whats-new-textarea">		
 *    <?php do_action( 'whats_new_textarea' ); ?>				
 * </div>
 * 
 * and re-enable the following code
 */
/*
function jmbp_whats_new_tiny_editor()
{
    // deactivation of the visual tab, so user can't play with template styles
    //add_filter ( 'user_can_richedit' , create_function ( '$a' , 'return false;' ) , 50 );
    
    $content = '';

    // building the what's new textarea
    if (isset($_GET['r'])) :
        $content = esc_textarea($_GET['r']);
    endif;

    // adding tinymce tools
    $editor_id = 'whats-new';
    $settings = array(
        'textarea_name' => 'whats-new',
        'teeny' => true,
        'media_buttons' => true,
        'drag_drop_upload' => true,
        'quicktags' => array(
            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'));

    // get the editor	
    wp_editor($content, $editor_id, $settings);
}
add_action('whats_new_textarea', 'jmbp_whats_new_tiny_editor');
*/


/*
 *  2. Admin enhancements:
 *  - allow bulk add users to groups
 */

/*
 * Allow bulk addition of users to Buddypress groups in backend admin screen
 * as per eg: https://gist.github.com/rohmann/6151699
 */
add_action('load-users.php', function() {
    if (isset($_GET['action']) && isset($_GET['bp_gid']) && isset($_GET['users'])) {
        $group_id = $_GET['bp_gid'];
        $users = $_GET['users'];
        foreach ($users as $user_id) {
            groups_join_group($group_id, $user_id);
        }
    }
//Add some Javascript to handle the form submission
add_action('admin_footer', function() {
        ?>
<script>
jQuery("select[name='action']").append(jQuery('<option value="groupadd">Add to BP Group</option>'));
jQuery("#doaction").click(function (e) {
    if (jQuery("select[name='action'] :selected").val() == "groupadd") {
                e.preventDefault();
        gid = prompt("Please enter a BuddyPres Group ID", "1");
        jQuery(".wrap form").append('<input type="hidden" name="bp_gid" value="' + gid + '" />').submit();
    }
});
</script>
        <?php
    });
});


/*
 *  3. User Profile enhancements: additional fields display and Search integration
 */

/* 
 * add additional profile fields to user listings 
 * 
 * @param array     $args
 */
function jmbp_display_member_profile_fields($args)
{
    global $members_template;

    if (!bp_is_active('xprofile')) {
        return false;
    }

    // Declare local variables.
    $data = false;

    // Guess at default $user_id.
    $default_user_id = 0;
    if (!empty($members_template->member->id)) {
        $default_user_id = $members_template->member->id;
    } elseif (bp_displayed_user_id()) {
        $default_user_id = bp_displayed_user_id();
    }

    // If we're in a members loop, get the data from the global.
    if (!empty($members_template->member->profile_data)) {
        $profile_data = $members_template->member->profile_data;
    }

    // Otherwise query for the data.
    if (empty($profile_data) && method_exists('BP_XProfile_ProfileData', 'get_all_for_user')) {
        $profile_data = BP_XProfile_ProfileData::get_all_for_user($default_user_id);
    }

    // If we're in the members loop, but the profile data has not
    // been loaded into the global, cache it there for later use.
    if (!empty($members_template->member) && empty($members_template->member->profile_data)) {
        $members_template->member->profile_data = $profile_data;
    }

    $args['user_id'] = $default_user_id;
    if (bp_has_profile($args)) {

        //profile-loop resets bp_has_profile to the display user, which we don't have so inserting code here:			
        //bp_get_template_part( 'members/single/profile/profile-loop' );
        while (bp_profile_groups()) : bp_the_profile_group();
            ?>  <?php if (bp_profile_group_has_fields()) : ?>
                    <?php
                    /** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
                    do_action('bp_before_profile_field_content');

                    ?>

                <div class="bp-widget <?php bp_the_profile_group_slug(); 
                ?>"><!--<?php bp_the_profile_group_name(); ?>--><div class="profile-fields">
                <?php while (bp_profile_fields()) : bp_the_profile_field(); ?>
                    <?php
                    if (bp_field_has_data()) :
                        $fieldname = bp_get_the_profile_field_name();
                        if ($fieldname == 'Name') {
                            $fieldname = 'Username';
                            $fieldvalue = '@' . bp_core_get_username($default_user_id);
                        } else {
                            $fieldvalue = bp_get_the_profile_field_value();
                        }

                        ?><div<?php bp_field_css_class(); 
                                ?>><span class="label"><?php echo($fieldname); 
                                ?></span> : <span class="data"><?php echo($fieldvalue); 
                                ?></span></div>
                    <?php endif; ?>
                    <?php
                    /**
                     * Fires after the display of a field table row for profile data.
                     *
                     * @since 1.1.0
                     */
                    do_action('bp_profile_field_item');
                    ?>
                <?php endwhile; ?></div></div>
                <?php
                /** This action is documented in bp-templates/bp-legacy/buddypress/members/single/profile/profile-wp.php */
                do_action('bp_after_profile_field_content');
                ?>
            <?php endif; ?>
        <?php
        endwhile;
    }
    
    //now output user's buddy groups or buddy groups link
    echo jmbp_buddy_pairs_link($default_user_id);
    
    //now output button to add user to selected group, if appropriate
    echo jm_member_add_link($default_user_id);
}
add_action('bp_directory_members_item', 'jmbp_display_member_profile_fields');
add_action('bp_group_members_list_item', 'jmbp_display_member_profile_fields');


/* 
 * if we are in the members screen looking for members for a group
 * calculate the correct add link for each member
 * 
 * @param array     $args should include 'user_id'
 */
function jm_member_add_link($user_id)
{
    $group=intval(jmbp_get_param('g'));
    $link = '';
    if ($group){
        $alreadyMember=false;
        if ($group==-1){
            //security check for new group, students not allowed to create new group without teacher
            if (! jmbp_create_groups_restriction(false, true) ) {return;}
            
            if (jmbp_querystring_id_exists('u', $user_id)){
                $alreadyMember = __('Already added', 'buddypairs');
            } else {
                //no group exists so will return to the same screen with the user added
                $link = '?' . jmbp_querystring_add_id('u', $user_id);
            }
        } else {
            //if group id is provided and not -1, instantiate it
            $groupobj = groups_get_group($group);
            $group_id = $groupobj->id;

            //security check for existing group, exit if current user not a moderator/admin
            if (! bp_groups_user_can_send_invites( $group_id )) {return false;}

            if (groups_is_user_member($user_id, $group_id)){
                $alreadyMember = __('Already Member', 'buddypairs');
            } else {
                $_REQUEST['u'] = $user_id; 
                $link = '?' . http_build_query($_REQUEST);
            }
        }
        if ($alreadyMember){            
            /* 
             *     @type string      $button_element    Optional. Button element. Default: 'a'.
             *     @type array       $button_attr       Optional. Button attributes. 
            */
            $button_args = array(
                'id'         => 'add_user',
                'component'  => 'groups',
                'link_text'  => $alreadyMember,
                'link_class' => 'group-add no-ajax',
                'link_href'  => '',
                'button_element' => 'button',
                'button_attr' => array('disabled' => 'disabled'),
                'wrapper'    => 'div',
                'parent_attr' => array('class' => 'action', 'disabled' => 'disabled'),
                'block_self' => false,
            );
            return bp_get_button( $button_args);            
        }
        if ($link){
            $button_args = array(
                'id'         => 'add_user',
                'component'  => 'groups',
                'link_text'  => __( 'Add to Group', 'buddypairs' ),
                'link_class' => 'group-add no-ajax',
                'link_href'  => $link,
                'wrapper'    => 'div',
                'parent_attr' => array('class' => 'action',),
                'block_self' => false,
            );
            return bp_get_button( $button_args);
        }
    }
}

/* 
 * output info on buddy pairs groups
 * 
 * @param array     $args should include 'user_id'
 */
function jmbp_buddy_pairs_link($user_id){
    
    if (! $user_id){$user_id= bp_current_user_id();}
    
    $args['type']='pair';
    //$groups = groups_get_user_groups($userid); or get_group_ids do not support query by type
    $groups = jmbp_get_buddy_groups($user_id);
    $additionalGroups = sizeof($groups) - 1;

    if (sizeof($groups)>0){

        //TODO: maybe simplify group name by removing current user name from display
        $group = groups_get_group($groups[0]);
        $groupName = $group->name;
        echo('<span class="data"><a href="' );
        //if more than 1 buddy group, link to the list of pair groups 
        if ($additionalGroups){
            echo (bp_core_get_userlink($user_id, false, true) . '/groups/?group_type=pair">');
            echo ($groupName);            
            echo (' (+' . $additionalGroups . ')');
        } else {
            //otherwise output link to the individual group
            $group_permalink = trailingslashit( bp_get_root_domain()) . trailingslashit( bp_get_groups_root_slug() ) . $group->slug . '/' ;
            echo($group_permalink . '">');
            echo ($groupName);            
        }
        echo('</a></span>');
    } else {
        echo('<span class="data"><a href="');
        if (jmbp_is_teacher_of_student($user_id, bp_loggedin_user_id())){
            
            $createlink = trailingslashit( bp_get_members_directory_permalink()) . 
            '?g=-1&u=' . $user_id .  '&type=pair';                
            echo($createlink . '">');
            _e('No buddy pairs', 'buddypairs');
            echo(' - ');
            _e('Create buddy pair', 'buddypairs');            
        }else{
            echo(bp_core_get_userlink($user_id, false, true) . '/groups/">');
            _e('No buddy pairs', 'buddypairs');
            echo(' - ');
            _e('View all groups', 'buddypairs');
        }
        echo('</a></span>');
    }    
}

function jmbp_get_buddy_groups($user_id){

    //sadly, bp_has_groups sets global $groups_template which seems to cause problems later 
    //(apache runs at 100% and never finishes page)
    //if (bp_has_groups($args)){
    //so instead, 
    //use the built in function groups_get_user_groups and filter by bp_groups_get_group_type
    //at least these built in functions can use buddypress data caching mechanism

    //returns array containing groups and total
    $result = groups_get_user_groups($user_id);
    $pairs = null;
    if (is_array($result)){
        $groups = $result['groups'];
        if (is_array($groups)){
            foreach ($groups as $group){
                if (bp_groups_get_group_type($group, true)=='pair'){
                    $pairs[] = $group;
                }
            }
        }
    }
    return $pairs;
}

/*
 *  adds link to member's school
 */
function jmbp_my_school_link()
{
    if (is_user_logged_in()) {
        $args = array(
            'field' => 'School',
            'user_id' => bp_loggedin_user_id()
        );
        $school = bp_get_profile_field_data($args);
        echo('<li id="members-school"><a href="?members_search=' . urlencode($school) . '">'. 
            __('My School', 'buddypairs') . '</a></li>');
    }
}
add_action('bp_members_directory_member_types', 'jmbp_my_school_link');


/*
 *  4. BuddyPairs permissions handling: jmbp_can_manage, jmbp_is_teacher_of_student, jmbp_same_school
 */

/*
 * Custom groups restriction: BuddyPress only supports Admins or Everyone
 * This function disallows students to create groups: the idea is to enforce every group to have teacher
 * 
 * @param bool $can_create  Whether the person can create groups.
 * @param int  $restricted  Whether or not group creation is restricted.
 * 
 * return bool  can create or not
 */
function jmbp_create_groups_restriction($can_create, $restricted)
{
    if (($restricted) && !($can_create)) {
        $current_member_type = bp_get_member_type(bp_loggedin_user_id());
        if ($current_member_type != 'student') {
            $can_create = true;
        }
    }
    return $can_create;
}
add_filter('bp_user_can_create_groups', 'jmbp_create_groups_restriction', 10, 2);


/*
 * allow user impersonation when teacher is on student's profile: 
 *  - this doesn't help with group actions since these navigate away from student profile 
 *    so the impersonation is lost
 * and for actions within the profile, jmbp_can_manage is better solution
 * @param int   $id		detected logged in user it
 * 
 * return int	user id to return for as logged in user
 */
/*
function jmbp_teacher_as_current_user($id)
{
    //for temporarily disabling
    return $id;
    
    $incall = true;
    static $incall;
    if ($incall) {return $id;}
    $incall = true;
    global $bp;
    $display_user = bp_displayed_user_id();  //can result in recursive calls

    //if there is a display user (we are looking at a user profile) who is student of current user
    //then allow the current user (the teacher) to act on behalf of the student
    if (($display_user) && ($display_user != $id) && (jmbp_is_teacher_of_student($display_user, $id))) {
        //additional filter could be needed here
        $incall = false;
        return $display_user;        
    } else {
        $incall = false;
        return $id;
    }
}
add_filter('bp_loggedin_user_id', 'jmbp_teacher_as_current_user', 1);
*/

/*
 * jmbp_can_manage permissions modification, if user does not have permission on action,
 * grant the additional permission if:
 * -  the current user is a Teacher viewing a Pupil from the same school
 * 
 * @param bool		$retval			result of initial permission check
 * @param string    $capability     Capability or role name.
 * @param int		$site_id		Blog ID. Defaults to the BP root blog.
 * @param array|int $args {
 *     Array of extra arguments applicable to the capability check.
 *     @type mixed $a,...   Optional. Extra arguments applicable to the capability check.
 * }
 * @return bool True if the user has the cap for the given parameters.
 */
function jmbp_can_manage($retval, $capability, $site_id, $args)
{

    static $in_call;
    if ($in_call) {
        return $retval;
    }
    $in_call = true;

    //not logged in, no permissions
    if (!is_user_logged_in()) {
        $in_call = false;
        return false;
    }

    //if permission is already granted, allow it, this function only elevates certain permissions
    if ($retval) {
        $in_call = false;
        return true;
    }

    // Super admin can always manage. but this should already be checked so don't check again
    if ( bp_current_user_can( 'bp_moderate' ) ) {return $retval;}
    //now check the teacher-pupil relationship
    $retval = jmbp_is_teacher_of_student();
    $in_call = false;
    return $retval;
}
add_filter('bp_current_user_can', 'jmbp_can_manage', 10, 4);

/**
 * can the teacher supervise the pupil
 *
 * @param int    $student       user id, defaulting to display user
 * @param int    $teacher   user id, defaulting to logged in user
 * 
 * @return bool	 $teacher is a teacher at the same school as student
 */
function jmbp_is_teacher_of_student($student = 0, $teacher = 0)
{
    if ( bp_current_user_can( 'bp_moderate' ) ) {return true;}

    //if BuddyPress isn't sufficiently initialized ids won't be available
    if (!($teacher)) {
		//$teacher=bp_loggedin_user_id(); //ideally, don't use BuddyPress function so we can hook this to allow teacher to impersonate student
        $teacher = get_current_user_id(); 
        if (!($teacher)) {
            return false;
        }
    }
    if (!($student)) {
        $student = bp_displayed_user_id();
        if ($student == 0) {
            return false;
        }
        if ($student == $teacher) {
            return false;
        } //student can't supervise themselves
    }

    //check the teacher and student are actually teacher and student
    $student_member_type = bp_get_member_type($student);
    if ($student_member_type != 'student') {
        return false;
    }

    $teacher_member_type = bp_get_member_type($teacher);
    if ($teacher_member_type != 'teacher') {
        return false;
    }

    //if they are in the same school then teacher is valid teacher for student
    return jmbp_same_school($student, $teacher);
}

/**
 * are the two users from the same school?
 *
 * @param int    $testuser       user id, defaulting to display user
 * @param int    $loggedinuser   user id, defaulting to logged in user
 * 
 * @return bool	 users are from the same school
 */
function jmbp_same_school($testuser, $loggedinuser)
{
    //if initialisation routines are not complete, check for school will fail
    global $bp;
    if (!(isset($bp->profile->table_name_groups))) {
        return false;
    }

    if (!($loggedinuser)) {
        $loggedinuser = get_current_user_id();
    }
    if (!($testuser)) {
        $testuser = bp_displayed_user_id();
        if ($testuser == 0) {
            return false;
        }
        if ($testuser == $loggedinuser) {
            return true;
        }
    }

    $school1 = xprofile_get_field_data('School', $loggedinuser, 'comma');
    if (!($school1)) {
        return false;
    }
    $school2 = xprofile_get_field_data('School', $testuser, 'comma');
    if (!($school2)) {
        return false;
    }

    return ($school1 == $school2 ? true : false);
}

/*
 * Allow display user to be set from parameter du
 * Normally, if we are not on the user profile page, there is no display user: 
 * this function allows links from user profile to carry display user information
 * so that certain links could operate on behalf of previously displayed user
 * 
 * @param int $id ID of the currently displayed user.
 * 
 * @return bool	 modified user id
 */
function jmbp_displayed_user_id($id)
{
    //if we are not on a user profile page and the parameter is set then get it
    if (! ($id)){
       $id = absint(jmbp_get_param('du'));
    }
    return $id;
}
add_filter('bp_displayed_user_id', 'jmbp_displayed_user_id', 10, 1);

/*
 * get parameter, from GET or POST
 * 
 * @param string parameter name
 * 
 * @return string	value
 */
function jmbp_get_param($param)
{
    $value = '';
    if (isset($_REQUEST[$param])) {
         $value = $_REQUEST[$param];        
     } else {
         if (isset($_COOKIE['bps_request'])){
             $qs=$_COOKIE['bps_request'];
             parse_str($qs, $paramArray);
             if (isset($paramArray[$param])){
                 $value=$paramArray[$param];
             }
         }
     }
    return $value;    
}

/*
 * LIMITATION / TODO:  Although Teachers and Admins can manage the student's user profile, 
 * the system doesn't currently allow anyone to accept Group or Friendship invitations 
 * on behalf of other users, not even Admins, therefore this is not extended to Teachers
 * 
 * There are several possible solutions to this involving more highly customized code to either:
 * - auto-accept invitations (or maybe auto-accept if invitee is student 
 *   and inviter is teacher from the same school)
 * - create additional page / parameters and forked copy of relevant BuddyPress code path 
 *   which does allow this type of update
 * 
 * Preferred solution:
 *   - directly add to Groups without using invitation mechanism
 *   - turn off Friends component since it is only used for 
 *      - Invite Friends to Groups: not used, we want to invite anyone
 *      - Private Messaging: not used, we want supervised messaging
 * 
 * Remainder of this section filters for links that would not work as expected 
 * when viewing other users profile
 */

/**
 * Filters the HTML button for joining a group.
 * *
 * @param string $button HTML button for joining a group.
 * @param object $group BuddyPress group object
 */
function jmbp_join_button($button, $group)
{
    if (get_current_user_id() != bp_displayed_user_id()) {
        //we are viewing someone else's profile, so don't show the leave group button, 
        //that's confusing - not clear if displayed user or logged in user is going to leave group..
        if ($button['id'] == __('leave_group', 'buddypairs')) {
            return '';
        }
    }
    return $button;
}
add_filter('bp_get_group_join_button', 'jmbp_join_button', 10, 2);

/**
 * Filters the full-text description for a specific notification.
 *
 * @param string $description  Full-text description for a specific notification.
 * @param object $notification Notification object.
 * 
 * return string	modified description
 */
function jmbp_notification_correct_userdomain($description, $notification)
{
    if (get_current_user_id() != bp_displayed_user_id()) {
        $description = jmbp_correct_userdomain($description);
    }
    return $description;
}
add_filter('bp_get_the_notification_description', 'jmbp_notification_correct_userdomain', 10, 2);

/**
 * if display user is not logged on user, swap certain string references to refer to display user
 *
 * @param string $output  pre-generated output string
 * 
 * return string	modified output
 */
function jmbp_correct_userdomain($output)
{
    $display_user = bp_displayed_user_id();
    if (( $display_user != 0 ) && ( get_current_user_id() != $display_user )) {
        $bp = buddypress();
        $logged_in_user_slug = $bp->loggedin_user->domain;
        $display_user_slug = $bp->displayed_user->domain;
        $output = str_replace($logged_in_user_slug, $display_user_slug, $output);
    }
    return $output;
}

/**
 * if display user is not logged on user, swap certain string references to refer to display user
 *
 * @param string $output					pre-generated output string
 * @param int		 $friendship_id		id of friendship request (0 if we are viewing someone else's request)
 * 
 * return string	modified output
 */
function jmbp_correct_friendship_link($output, $friendship_id)
{
    if (!( $friendship_id )) {
        $output = '" onclick="' . "alert('" .
            __('Sorry you cannot manage requests on behalf of another user.', 'buddypairs') . "');return true;" . '"';
        /* this actually output the right link but is still not accepted by the system, because when you click the link,
         * again the system will interpret current user on the new link.
          global $members_template;
          if ( !$friendship_id = wp_cache_get( 'friendship_id_' . $members_template->member->id . '_' . bp_displayed_user_id() ) ) {
          $friendship_id = friends_get_friendship_id( $members_template->member->id, bp_displayed_user_id() );
          wp_cache_set( 'friendship_id_' . $members_template->member->id . '_' . bp_displayed_user_id(), $friendship_id, 'bp' );
          }
          if ($friendship_id){
          $output = str_replace('/?_', '/'. $friendship_id . '?_', $output);
          $output = jmbp_correct_userdomain($output);
          }
          else{
          $output='';
          }
         */
    }
    return $output;
}
//correct friend request links
add_filter('bp_get_friend_accept_request_link', 'jmbp_correct_friendship_link', 10, 2);
add_filter('bp_get_friend_reject_request_link', 'jmbp_correct_friendship_link', 10, 2);




/*
 *  5. Creation of New Groups customization/simplification of process
 * 
 * Querystrings are now supported so links can be used to 
 *  create groups with certain parameters, eg:
 * ?du=8&name=New+Group&status=private&type=pair&desc=Group+Description
 * 
 * Parameters are all optional and include:
 * du=display user      seed users for group
 * name=new group name  for pair group defaults to Buddy Pair and user names
 * desc=description     if not supplied, defaults to group name
 * status=private|hidden|public  by default, 
 *              buddy pair groups will be private or hidden (unlisted in group directory)
 *          (the problem with hidden groups is teachers might not have access to see them)
 *              all other groups should be private
 * type=pair    only pair is supported currently as shortcut for buddy pair group
 */



/**
 * BuddyPairs default new groups to private
 * 
 * return string	new group status
 */
function jmbp_get_new_group_status()
{
    //status is always set by the time we get here...?
    /*
      $bp     = buddypress();
      $status = isset( $bp->groups->current_group->status )
      ? $bp->groups->current_group->status
      : 'private';
     */
    $status = jmbp_get_param('status');
    if (''==$status){
        $type=jmbp_get_param('type');
        if ($type=='pair'){
            //the problem with hidden groups is teachers might not have access to see them
            //$status = 'hidden';            
            $status = 'private';
        } else {
            $status = 'private';
        }
    }
    return $status;
}
//return apply_filters( 'bp_get_new_group_status', $status );
add_filter('bp_get_new_group_status', 'jmbp_get_new_group_status');


/*
 * standardize title string for Buddy Pair groups
 * 
 * @param mixed $studentids		id or list of ids of students in group
 */
function jmbp_makeBuddyGroupTitle($studentids)
{
    $title='Buddy Pair ';
    if (! $studentids){
        $studentids = jmbp_get_param('du');
    } 
    if (is_string($studentids)){
        $studentids = explode(',', $studentids);
    }
    foreach($studentids as $id){
        if (bp_get_member_type($id)== 'student'){
            $title .= bp_core_get_username($id) . ' ';
        }
    }
    return $title;
}

/**
 * make new groups have document libraries without requiring additional creation step
 * 
 * return bool	create the document library
 */
function jmbp_create_docs_for_group()
{
    return true;
}
add_filter('bp_docs_force_enable_at_group_creation', 'jmbp_create_docs_for_group', 10, 1);

/**
 * this sets the default value for whether forums are enabled for new group 
 * it pre-selects the value in the group creation step - it doesn't skip the step
 * it also doesn't work - it makes the value selected but the forum doesn't get created...
 * TODO: check instead:  
 *  - function groups_action_create_group() in bp-group/bp-groups-action.php
 *  - groups/create.php template section if ( function_exists('bp_forums_setup') ) 
 *  - see also https://buddypress.org/support/topic/removing-a-step-from-the-group-creation-process/
 * 
 * @param bool $forum					currently enabled or not
 * 
 * return bool	enabled or not
 */
function jmbp_default_enable_forum($forum)
{
    return true;
}
add_action('bp_get_new_group_enable_forum', 'jmbp_default_enable_forum', 10, 1);

/*
 * 
 * @param string $name Name of the new group.
 * 
 * return string	modified name
 */
function jmbp_get_new_group_name($name)
{
    if (! ($name)) {
        $name = jmbp_get_param('name');
        if ($name==''){
            $type=jmbp_get_param('type');
            if ($type=='pair'){
                $name =  jmbp_makeBuddyGroupTitle(0);
            }
        }
    }
    return $name;    
}
add_filter('bp_get_new_group_name', 'jmbp_get_new_group_name', 10, 1);

/*
 * 
 * @param string $description description of the new group.
 * 
 * return string	modified description
 */
function jmbp_get_new_group_description($description)
{
    if (! ($description) ) {
        $description = jmbp_get_param('desc');
        if (! ($description) ) {
            $description = jmbp_get_new_group_name('');
        }
    }
    return $description;    
}
add_filter('bp_get_new_group_description', 'jmbp_get_new_group_description', 10, 1);

/*
 *  fires after first save of group
 *  here display user set from &du= would be lost as not included in POST
 */
function jmbp_groups_create_group_step_save_group_details()
{
    //get the group
	$bp = buddypress();
    $group_id=$bp->groups->new_group_id;    
    $group = groups_get_group( $group_id );
    $bp->groups->current_group = $group;
    
    //default to private, and set by mods only
    $group->status=jmbp_get_new_group_status();
    groups_update_groupmeta( $group_id, 'invite_status', 'mods');    
    
    $groupType = jmbp_get_param('type');
    if ($groupType){
        bp_groups_set_group_type( $group_id, $groupType );
    }
    
    //enable forum
    $group->enable_forum=true;
    $group->save();

    $forum_id=intval(groups_get_groupmeta( $group->id, 'forum_id' ));
    if ( !($forum_id) ) {
        jmbp_auto_create_forum( $group);
    }
    
    //add saved users
    $studentids = jmbp_get_param('du');
    $studentids = explode(',', $studentids);
    foreach($studentids as $id){
        jmbp_add_user_to_group($id, $group_id);
        if (bp_get_member_type($id)== 'teacher'){
            groups_promote_member($id, $group_id, 'mod');
        }
    }
    
}
add_action('groups_create_group_step_save_group-details', 'jmbp_groups_create_group_step_save_group_details');

/*
 * fires on display of group creation screen
 * 
 */
function jmbp_after_group_details_creation_step()
{
    //keep the display user if this was set
?><input type="hidden" name="du" 
value="<?php echo(jmbp_get_param('du'));?>"/>
<input type="hidden" name="status" 
       value="<?php echo(jmbp_get_param('status'));?>"/>
<input type="hidden" name="type" 
       value="<?php echo(jmbp_get_param('type'));?>"/><?php   
}
add_action('bp_after_group_details_creation_step', 'jmbp_after_group_details_creation_step');

/**
 * Adds user to group using groups_accept_invite which also works without previous invite
 *
 * Returns true groups_accept_invite completes.
 *
 * @since 1.0.0
 *
 * @param int $user_id  ID of the user.
 * @param int $group_id ID of the group.
 * @return bool True when the user is a member of the group, otherwise false.
 */

function jmbp_add_user_to_group($user_id, $group_id) {

  if (!$user_id){
      return false;
  } 
  return groups_accept_invite($user_id, $group_id);
}

    

/*
 *  ** off-cuts, alternative drafts **
 *  This section includes code which is not needed in current configuration but could be needed
 *  if configuration changes in future.
 */

/*
 * groups auto-accept invite using groups_accept_invite?
 */
/*
 * Auto-accept friend request:  
 * TODO: clear email notifications, currently sent for both request and acceptance
 */
/*
  function bp_auto_accept_friend_request( $friendship_id, $friendship_initiator_id, $friendship_friend_id ) {
  $friendship_status = BP_Friends_Friendship::check_is_friend( $friendship_initiator_id, $friendship_friend_id );
  if ( 'pending' == $friendship_status ) {
  // force add
  friends_add_friend( $friendship_initiator_id, $friendship_friend_id, $force_accept = true );
  friends_accept_friendship( $friendship_id );

  // Update friend totals - err, this seems to double the totals...
  //friends_update_friend_totals( $friendship_initiator_id, $friendship_friend_id, 'add' );
  // Remove the friend request notice
  bp_core_delete_notifications_for_user_by_item_id( $friendship_friend_id, $friendship_initiator_id,
  'friends', 'friendship_request' );
  }
  }
  add_action('friends_friendship_requested', 'bp_auto_accept_friend_request', 200, 3);
 */

/* add favourite counts to items - this could be slow though and now testing alternative component */
/*
  function activity_favorite_count() {
  $activity_fav_count = bp_activity_get_meta( bp_get_activity_id(), 'favorite_count' );

  if ($activity_fav_count >= 1) : {

  if (is_user_logged_in()) : {
  echo '<span>(' . $activity_fav_count . ')</span> favorite';
  if ($activity_fav_count > 1) :
  {echo 's';}  //this turns favorite into favorites :)
  endif;
  }
  endif;
  }
  endif;
  }

  add_action( 'bp_activity_entry_meta', 'activity_favorite_count' );
 */

/*  search customization
  function expert_loop_querystring( $query_string, $object ) {
  if ($object != 'members')  return $query_string;
  if ( ! empty( $query_string ) ) {
  $query_string .= '&';
  }

  $query_string .= 'type=alphabetical&include='.include_experts();

  return $query_string;
  }
  add_filter( 'bp_ajax_querystring', 'expert_loop_querystring', 20, 2 );
 */

/* turned off: not working effectively */
/*function bp_admin_is_my_profile( $my_profile ){
//  global $bp;
//  if( 
//    ( $bp->current_component == bp_get_notifications_slug() || $bp->current_component == bp_get_messages_slug()  ) && 
//    current_user_can('manage_options') ){ 
//        return true;
//  }
//  else {
//    return $my_profile;
//  }
//}
//add_filter('bp_is_my_profile', 'bp_admin_is_my_profile', 1);
*/


/* 
 * Skip the Gravatar check when using a default BP avatar to increase privacy 
 * and avoid delays especiall for China users
 * (currently not necessary since the Cat Generator Avatars are enabled)
 */
//add_filter( 'bp_core_fetch_avatar_no_grav', '__return_true' );

/* 
 * Note: bp_is_active check is recommended but not applicable here since BP is not loaded yet
 * Code which adds actions and filters is safe and will simply not get called if BP is not enabled
 * If a standard wordpress filter is hooked and a BP function is used then check bp_is_active
 * 
  if ( function_exists('bp_is_active') ) {
  }
 */

/*
 *  Removes settings group creation step 
 *  (though doesn't change the default value..)
 */
function bp_remove_group_step_settings($array) {
	
	$array = array(
		'group-details'  => array(
			'name'       => _x( 'Details', 'Group screen nav', 'buddypress' ),
			'position'   => 0
		)
	);
	
	return $array;
}
add_filter ('groups_create_group_steps', 'bp_remove_group_step_settings', 10, 1);


/**
 * Filter the list of valid groups statuses to disallow public.
 * doesn't work, doesn't filter Group Settings
 *
 * @param array $value Array of valid group statuses.
 */
/*
function jmbp_groups_valid_status($value){
    return array(
//			'public',
			'private',
			'hidden'
		);
}
add_filter('groups_valid_status', 'jmbp_groups_valid_status', 10, 1);
*/

function jmbp_bp_custom_group_types() {
    bp_groups_register_group_type( 'pair', array(
        'labels' => array(
            'name' => __('Buddy Pairs', 'buddypairs'),
            'singular_name' => __('Buddy Pair', 'buddypairs')
        ),
 
        // New parameters as of BP 2.7.
        'has_directory' => true,
        'show_in_create_screen' => false,
        'show_in_list' => true,
        'description' => __('Buddy Pair study group', 'buddypairs'),
        'create_screen_checked' => false
    ) );

    bp_groups_register_group_type( 'flex', array(
        'labels' => array(
            'name' => __('Flexible groups', 'buddypairs'),
            'singular_name' => __('flexible group', 'buddypairs')
        ),
 
        // New parameters as of BP 2.7.
        'has_directory' => true,
        'show_in_create_screen' => false,
        'show_in_list' => true,
        'description' => __('flexible groups for schools, study groups', 'buddypairs'),
        'create_screen_checked' => false
    ) );
    
}
add_action( 'bp_groups_register_group_types', 'jmbp_bp_custom_group_types' );


/*
 * create forum for group 
 */
function jmbp_auto_create_forum($group){

    //code as per plugins/bbpress/includes/extend/buddypress/groups.php l399+
    // Set the default forum status
    switch ( $group->status ) {
        case 'hidden'  :
            $status = bbp_get_hidden_status_id();
            break;
        case 'private' :
            $status = bbp_get_private_status_id();
            break;
        case 'public'  :
        default        :
            $status = bbp_get_public_status_id();
            break;
    }
    
    
    // Create the initial forum
    $forum_id = bbp_insert_forum( array(
        'post_parent'  => bbp_get_group_forums_root_id(),
        'post_title'   => $group->name,
        'post_content' => $group->description,
        'post_status'  => $status
    ) );

    $group_id = $group->id;
    bbp_add_forum_id_to_group( $group_id, $forum_id );
    bbp_add_group_id_to_forum( $forum_id, $group_id );
}

/*
 * Adds a sub-menu to User Profile Groups to indicate the current users Pair memberships
 */
function jmbp_add_tabs()
{
    $link = bp_core_get_userlink(bp_displayed_user_id(), false, true) . 'groups/?group_type=pair';
	bp_core_new_subnav_item( array(
	    'name'            => __('Pairs', 'buddypairs'),
	    'slug'            => 'pairs',
	    'parent_url'      => 'groups',
	    'parent_slug'     => 'groups',
	    'screen_function' => 'false',
	    'position'        => 1,
	    'link'            =>  $link
	    )
	);

    if (jmbp_is_teacher_of_student(bp_displayed_user_id(), bp_loggedin_user_id())){
        $link =  trailingslashit( bp_get_members_directory_permalink()) . 
    '?g=-1&u=' . jmbp_displayed_user_id(bp_displayed_user_id()) .  '&type=pair';     
        bp_core_new_subnav_item( array(
            'name'            => __('Create Pair', 'buddypairs'),
            'slug'            => 'createpair',
            'parent_url'      => 'groups',
            'parent_slug'     => 'groups',
            'screen_function' => 'false',
            'position'        => 5,
            'link'            =>  $link
            )
        );
    }
} 
add_action( 'bp_setup_nav', 'jmbp_add_tabs', 100 );


/*
 * add the buddy pairs link to the main user profile view
 */
function jmbp_add_profile_buddy_link()
{
    //this is called from member listing as well as user profile listing
    //this call checks we are on actual user profile page
    $user_id = bp_current_user_id();
    if ($user_id){
        echo(jmbp_buddy_pairs_link($user_id));
    }
}
add_action( 'bp_after_profile_field_content', 'jmbp_add_profile_buddy_link');

/*
 * link for creating buddy group
 */
function jmbp_get_pairgroup_create_button() {

    //this is to link to member directory first to add users before creating group
    $createlink = trailingslashit( bp_get_members_directory_permalink()) . 
    '?g=-1&u=' . jmbp_displayed_user_id(bp_displayed_user_id()) .  '&type=pair';                

    //this is to link to groups directory 
    //$createlink = trailingslashit( bp_get_groups_directory_permalink() . 'create' ) . 
    //        'du=' . jmbp_displayed_user_id(bp_displayed_user_id()) .  '&type=pair'

    $button_args = array(
        'id'         => 'create_pair',
        'component'  => 'groups',
        'link_text'  => __( 'Create Buddy Pair', 'buddypairs' ),
        'link_class' => 'group-create no-ajax',
        'link_href'  => $createlink,
        'wrapper'    => false,
        'block_self' => false,
    );

    /**
     * Filters the HTML button for creating a group.
     *
     * @param array $button_args parameters for creating a group.
     */
    return bp_get_button( $button_args);
}

/*
 * Removes the ugly word Base from the User Profile fields display
 */
function jmbp_remove_profile_base_name($name){

    if ($name=='Base'){
        $name=__('User Profile', 'buddypress');
    }
    return $name;
}
add_filter ('bp_get_the_profile_group_name', 'jmbp_remove_profile_base_name', 10, 1);


/*
 * Adds query parameters to the advanced search form so current context (group to add etc)
 * is remembered after pressing Search.
 *
 * @param stdClass $F   container for fields used by BP Profile Search.
 */
function jmbp_add_group_invite_advanced_search($F)
{
    $group=jmbp_get_param('g');
    if ($group){
        $F->fields[] = bps_set_hidden_field ('g', $group);
    }
    
    $users=jmbp_get_param('u');
    if ($users){
        $F->fields[] = bps_set_hidden_field ('u', $users);
    }
    
    $type=jmbp_get_param('type');
    if ($type){
        $F->fields[] = bps_set_hidden_field ('type', $type);
    }
}
add_action ('bps_before_search_form', 'jmbp_add_group_invite_advanced_search');

/**
 * Adds query parameters to the simple search form so current context (group to add etc)
 * is remembered after pressing Search.
 *
 * @param string $search_form_html HTML markup for the member search form.
 */
function jmbp_add_group_invite_simple_search($search_form_html)
{
    $addMarkup='';
    $group=jmbp_get_param('g');
    if ($group){
       $addMarkup .= '<input type=hidden name="g" value="' .  $group . '"/>';
    }
    
    $users=jmbp_get_param('u');
    if ($users){
       $addMarkup .= '<input type=hidden name="u" value="' .  $users . '"/>';
    }
    
    $type=jmbp_get_param('type');
    if ($type){
       $addMarkup .= '<input type=hidden name="type" value="' .  $type . '"/>';
    }
    if ($addMarkup){
        $formClose="</form>";
        $search_form_html=str_replace($formClose, $addMarkup . $formClose, $search_form_html);
    }
    
    return $search_form_html;
}
add_filter ('bp_directory_members_search_form', 'jmbp_add_group_invite_simple_search', 10, 1);

/*
 *  Output message at the top of the group invite form if it is being used for new group
 */
function jmbp_group_invite_message(){
    $group=intval(jmbp_get_param('g'));
    if ($group){
        //for new groups, manage users to add
        if ($group==-1){
            
            //security check for new group, students not allowed to create new group without teacher
            if (! jmbp_create_groups_restriction(false, true) ) {return;}
            
            ?><p class="jmbp_group_invite_message"><?php
            _e('Selecting users to add to new', 'buddypairs');
            echo( ' ' . (jmbp_get_param('type')=='pair') ? ' ' . __('Buddy Pair', 'buddypairs') . ' ' : '' ); 
            _e('group', 'buddypress');
            _e(': ', 'buddypairs');
            ?><?php
            $users=jmbp_get_param('u');
            if ($users){
/*                ?><p class="jmbp_group_invite_message"><?php
                _e('Selected users:', 'buddypairs');
                ?><ul><?php
 */
                
                $userids = explode(',', $users);
                foreach ($userids as $user){
                    if (intval($user)){
                        ?><li><?php
                        echo (bp_core_get_user_displayname($user));
                        echo (' (' . bp_core_get_username($user) . ')');
                        echo ('<a href="?' . jmbp_querystring_add_id('u', $user) . '"> &nbsp; ');
                        _e('x Remove', 'buddypairs');
                        echo ('</a>');
                        ?></li><?php
                    }
                }
                ?></ul></p><?php            
            }
            
            $groupCreateLink=trailingslashit( bp_get_groups_directory_permalink() . 'create/step/group-details/' ) . 
                    '?du=' . $users . '&type=pair';
           
            $button_args = array(
                'id'         => 'group',
                'component'  => 'groups',
                'link_text'  => __( 'Proceed to Create Group', 'buddypairs' ),
                'link_class' => 'group no-ajax',
                'link_href'  => $groupCreateLink,
                'wrapper'    => false,
                'block_self' => false,
            );

            /**
             * Filters the HTML button for creating a group.
             *
             * @param array $button_args parameters for creating a group.
             */
            echo (bp_get_button( $button_args));
            ?>.</p><?php            
        } else {
            
            //if group id is provided and not -1, instantiate it
            $groupobj = groups_get_group($group);
            $group_id = $groupobj->id;
            //if group doesn't exist, group object is returned with zero id
            if ($group_id){
                
                //security check for existing group, exit if current user not a moderator/admin
                if (! bp_groups_user_can_send_invites( $group_id )) {return false;}
                
                //for existing group, add any requested users directly to group
                $addedUsers=false;
                $addedMembers=array();
                $addedMods=array();
                $users=jmbp_get_param('u');
                if ($users){
                    $userIds=explode(',', $users);
                    foreach ($userIds as $user) {
                        if (jmbp_add_user_to_group($user, $group_id)){
                            $addedUsers=true;
                            if (bp_get_member_type($user)== 'teacher'){
                                groups_promote_member($user, $group_id, 'mod');
                                $addedMods[]=$user;
                            } else {
                                $addedMembers[]=$user;
                            }
                        }
                    }
                }
                unset($_REQUEST['u']);
                if ($addedUsers) {
                    ?><p class="jmbp_group_invite_result"><?php                    
                    if (sizeof($addedMods)>0){
                        _e('Added the following moderators to the group:', 'buddypairs');
                        echo(' ' . implode(',', bp_core_get_user_displaynames($addedMods)) . '<br />');
                    }
                    if (sizeof($addedMembers)>0){
                        $message= __('Added the following student members to the group:', 'buddypairs');
                        $message .= ' ' . implode(',', bp_core_get_user_displaynames($addedMembers)) . '<br />';
                        echo ($message);
                        bp_core_add_message($message, 'success');
                    }
                    ?></p><?php                    
                }
                ?><p class="jmbp_group_invite_message"><?php _e('Select users to add to group', 'buddypairs');  
                echo(' <span class="data"><a href="' );
                //otherwise output link to the individual group
                $group_permalink = trailingslashit( bp_get_root_domain() . '/' . bp_get_groups_root_slug() . '/' . $groupobj->slug . '/' );
                echo($group_permalink . '">');
                echo($groupobj->name); 
                echo('</a></span>');
                ?><br /><?php          

                $button_args = array(
                    'id'         => 'group',
                    'component'  => 'groups',
                    'link_text'  => __( 'Return to Group', 'buddypairs' ),
                    'link_class' => 'group no-ajax',
                    'link_href'  => $group_permalink,
                    'wrapper'    => false,
                    'block_self' => false,
                );

                /**
                 * Filters the HTML button 
                 *
                 * @param array $button_args parameters for creating a group.
                 */
                echo (bp_get_button( $button_args));
                ?></p><?php          
            }
        }
    }    
}
add_action('bp_before_directory_members', 'jmbp_group_invite_message');


/*
 * remove id from key and return querystring
 *
 * @param string $key   key value to remove id from
 * 
 * @return string   querystring with parameter removed
 */
function jmbp_querystring_remove_id($key, $id){
    //get parameter
    $valueString=jmbp_get_param($key);
    //turn to array and remove the tiem
    $valueArray=explode(',', $valueString);
    if(($arraykey = array_search($id, $valueArray)) !== false) {
        unset($valueArray[$arraykey]);
    }
    //turn back to string, reset parameter and rebuild query
    $valueString = implode(',', $valueArray);
    $_REQUEST[$key] = $valueString; 
    return http_build_query($_REQUEST);
}

/*
 * add id to key and return querystring
 *
 * @param string $key   query parameter name
 * @param string $id    id to search for within key value
 * 
 * @return string   querystring with parameter added
 */
function jmbp_querystring_add_id($key, $id)
{
    //get parameter
    $valueString=jmbp_get_param($key);
    //turn to array and remove the tiem
    $valueArray=explode(',', $valueString);
    if(($arraykey = array_search($id, $valueArray)) == false) {
        $valueArray[]=$id;
    }
    //turn back to string, reset parameter and rebuild query
    $resultString = implode(',', $valueArray);
    $_REQUEST[$key] = $resultString; 
    $resultString = http_build_query($_REQUEST);
    $_REQUEST[$key] = $valueString;  //reset so it doesn't keep building up...
    return $resultString;
}

/*
 * Does the item already exist in this key
 *
 * @param string $key   query parameter name
 * @param string $id    id to search for within key value
 * 
 * @return int  array key if found or zero if not
 */
function jmbp_querystring_id_exists($key, $id)
{
    $valueString=jmbp_get_param($key);
    $valueArray=explode(',', $valueString);
    return array_search($id, $valueArray)   ;    
}



/*
 * Modify groups menu
 */
function jmbp_add_group_tabs()
{
    if ( bp_is_groups_component() && bp_is_single_item() ) {
        if (bp_groups_user_can_send_invites() ) {
            global $bp;
            $link = trailingslashit( bp_get_members_directory_permalink()) . 
                '?g=' .  $bp->groups->current_group->id;
            bp_core_new_subnav_item( array( 
                'name'          => __('Add Members', 'buddypairs'), 
                'slug'          => 'groupadduser', 
                'parent_url'    => bp_get_group_permalink( groups_get_current_group() ), 
                'parent_slug'   => bp_get_current_group_slug(), 
                'screen_function' => 'false', 
                'position'      => 65, 
                'link'          =>  $link,
                'item_css_id'   => 'adduser' 
                ), 'groups' 
            );    
        }
    }    
}
add_action('groups_setup_nav', 'jmbp_add_group_tabs', 100 );
