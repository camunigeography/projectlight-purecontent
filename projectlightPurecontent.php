<?php

# Project Light implementation for pureContent
class projectlight
{
	# Define global defaults
	private function defaults ()
	{
		# Specify available arguments as defaults or as NULL (to represent a required argument)
		return array (
			'siteTitle' => NULL,
			'taglineHtml' => false,	// Can contain HTML
			'logoHtml' => false,
			'shortName' => NULL,	// Used for search box
			'themeColour' => 2,		// 1: Dark blue / 2: Cambridge Blue / 3: Purple / 4: Green / 5: Orange / 6: Red / 7: Gray
			'internalDomain' => 'cam.ac.uk',
			'userApi' => false,
			'adminUsers' => array (),
			'googleAnalytics' => false,
			'googleSiteVerification' => false,
			'verticalBreadcrumbMaxDepth' => 2,	// 2 items is home then top-level only; set to false to have same as main breadcrumb trial
			'restrictedAreas' => array (),
			'criticalLinksHtml' => false,
			'headingFeedbackLink' => false,
			'headingWidgetsHtml' => false,
			'curatedFrontPage' => true,
			'fullWidthAreas' => array (),	// These paths get full width, breaking out of the fixed width
			'sidebarSections' => array ('/people/', '/research/projects/'),
		);
	}
	
	
	
	# Constructor
	public function __construct ()
	{
		# Load the settings file, from within the site
		require_once ('sitetech/.config.php');
		
		# Ensure the pureContent framework is loaded and clean server globals
		require_once ('pureContent.php');
		
		# Merge the settings
		$this->settings = $this->assignArguments ($settings, $this->defaults ());
		
		# Get the result from the function below
		list ($this->browserline, $this->locationline, $this->menusection, $this->menufile, $this->navigationHierarchy) = pureContent::assignNavigation ();
		
		# Add in custom page title / location line entries if this file has been embedded in an application
		if (isSet ($embeddedModeBrowserline)) {
			$dividingTextInBrowserLine = ' &#187; ';
			$this->browserline .= $dividingTextInBrowserLine . strip_tags ($embeddedModeBrowserline);
		}
		if (isSet ($embeddedModeLocationline)) {
			$dividingTextOnPage = ' &#187; ';
			$this->locationline .= $dividingTextOnPage . $embeddedModeLocationline;
		}
		
		# Determine current directory
		if (substr_count ($_SERVER['SCRIPT_FILENAME'], '/sitetech/') || substr_count ($_SERVER['SCRIPT_FILENAME'], '/common/php/')) {
	        $this->currentDirectory = dirname ($_SERVER['DOCUMENT_ROOT'] . $_SERVER['SCRIPT_URL'] . 'bogus');     // This works with :8080
		} else {
			$this->currentDirectory = dirname ($_SERVER['SCRIPT_FILENAME']);	// NB This uses SCRIPT_FILENAME for compatibility with mod-rewritten applications
		}
		
		# Header image: must be header.jpg, 883px x 292px, or the University standard of 885x432 in the same directory
		$this->headerImageHtml = false;
		$this->opengraphHtml = false;
		$headerImageFile = 'header.jpg';
        $filename = pathinfo ($_SERVER['SCRIPT_URL'], PATHINFO_FILENAME);
        if (is_readable ($this->currentDirectory . "/header-{$filename}.jpg")) {
            $headerImageFile = "header-{$filename}.jpg";
		}
		if (!is_readable ($this->currentDirectory . '/header.html') && is_readable ($this->currentDirectory . '/' . $headerImageFile)) {
			list ($width, $height) = getimagesize ($this->currentDirectory . '/' . $headerImageFile);
			if (($width == 883 && $height == 292) || ($width == 885 && $height == 432)) {
				$this->headerImageHtml = '<img src="' . $headerImageFile . '" alt="" width="100%" style="max-height: ' . $height . 'px;" />' . "\n";
				
				$currentFolder = str_replace ($_SERVER['DOCUMENT_ROOT'], '', $this->currentDirectory);        // NB DOCUMENT_ROOT will not be slash-terminated, as pureContent::cleanServerGlobals() will have been run
				#!# $currentFolder will be wrong for aliased directories
				$this->opengraphHtml = pureContent::socialNetworkingMetadata ($this->settings['siteTitle'], $twitterHandle = false, $currentFolder . '/' . $headerImageFile, false, html_entity_decode ($this->settings['siteTitle'] . $this->browserline, ENT_COMPAT, 'UTF-8'));
			}
		}
		if (is_readable ($this->currentDirectory . '/header.mp4')) {
			$this->headerImageHtml  = "\n\t\t\t\t" . '<video width="100%" poster="header.jpg" autoplay="autoplay" muted="muted" playsinline="playsinline">';
			$this->headerImageHtml .= "\n\t\t\t\t\t" . '<source src="header.mp4" type="video/mp4" />';
			$this->headerImageHtml .= "\n\t\t\t\t" . '</video>';
		}
		
		# Determine if an area is full-width
		$this->isFullWidth = false;
		foreach ($this->settings['fullWidthAreas'] as $fullWidthArea) {
			if (strpos ($_SERVER['REQUEST_URI'], $fullWidthArea) === 0) {	// i.e. str_starts_with ()
				$this->isFullWidth = true;
				break;
			}
		}
		
		# Get the file contents
		$this->fileContents = file_get_contents ($_SERVER['SCRIPT_FILENAME']);
		
		# Load box generation support
		#!# Remove when sites all moved to using shortcode, which loads the class itself
		require_once ('boxes.php');
	}
	
	
	# Getter for browser line
	#!# Rename to title
	public function getBrowserline ()
	{
		return $this->browserline;
	}
	
	
	# Getter for site title
	public function getSiteTitle ()
	{
		return $this->settings['siteTitle'];
	}
	
	
	# Getter for logo
	public function getLogoHtml ()
	{
		return $this->settings['logoHtml'];
	}
	
	
	# Getter for tagline
	public function getTaglineHtml ()
	{
		return '<p id="containerorganisation">' . $this->settings['taglineHtml'] . '</p>';
	}
	
	
	# Getter for short name
	public function getShortName ()
	{
		return $this->settings['shortName'];
	}
	
	
	# Getter for location line
	#!# Rename to breadcrumb trail
	public function getLocationline ()
	{
		return $this->locationline;
	}
	
	
	# Getter for restriction status
	public function getRestrictionIndicator ()
	{
		# End if not enabled
		if (!$this->settings['restrictedAreas']) {return false;}
		
		# Loop through each of the restricted areas, and show if a match
		foreach ($this->settings['restrictedAreas'] as $restriction) {
			$delimiter = '/';
			if (preg_match ($delimiter . '^' . addcslashes ($restriction, $delimiter) . $delimiter, $_SERVER['REQUEST_URI'])) {
				return '<a href="/computing/websites/restriction.html"><img src="/images/icons/shield.png" alt="Shield" title="Restricted content (click for more information)" /></a>';
			}
		}
        
        # Otherwise return false
        return false;
	}
	
	
	# Getter for critical links HTML
	public function getCriticalLinksHtml ()
	{
		# End if not enabled
		if (!$this->settings['criticalLinksHtml']) {return;}
		
		# Return the HTML
		return $this->settings['criticalLinksHtml'];
	}
	
	
	# Getter for feedback link
	public function getHeadingFeedbackLink ()
	{
		# End if not enabled
		if (!$this->settings['headingFeedbackLink']) {return;}
		
		# Return the HTML
		return $html = '<p class="headinglink"><a href="/contacts/webmaster.html" title="We welcome suggestions for improving this website, however small or large">Feedback / ideas</a></p>';
	}
	
	
	# Getter for heading widgets area
	public function getHeadingWidgets ()
	{
		# End if not enabled
		if (!$this->settings['headingWidgetsHtml']) {return;}
		
		# Return the HTML
		return $this->settings['headingWidgetsHtml'];
	}
	
	
	# Getter for page title, i.e. H1
	public function getPageTitle ()
	{
		# Get the contents from the main page content; $asHtml = true ensures that any image within the HTML is maintained
		require_once ('application.php');
		$pageTitle = application::getTitleFromFileContents ($this->fileContents, $startingCharacters = 100, $tag = 'h1', $asHtml = true);
		
		# As a fallback, try reading it dynamically
		#!# Should this use innerHtml now that $asHtml = true is set?
		if (empty ($pageTitle)) {
			$pageTitle = "
			<span id=\"pagetitledynamic\">&nbsp;</span>
			<script>
				window.onload = function () {
					var h1Tag = document.getElementById ('content').getElementsByTagName ('h1')[0].innerText;
					document.getElementById ('pagetitledynamic').innerText = h1Tag;
				};
			</script>";
		}
		
		# Return the page title HTML
		return $pageTitle;
	}
	
	
	# Getter for menu section
	public function getMenusection ()
	{
		return $this->menusection;
	}
	
	
	# Getter for menu file
	public function getMenufile ()
	{
		return $this->menufile;
	}
	
	
	# Getter for header image HTML
	public function getHeaderImageHtml ()
	{
		return $this->headerImageHtml;
	}
	
	# Getter for Open Graph HTML
	public function getOpengraphHtml ()
	{
		return $this->opengraphHtml;
	}
	
	
	# Google site verification
	public function googleSiteVerification ()
	{
		# End if not enabled
		if (!$this->settings['googleSiteVerification']) {return;}
		
		# Return the string
		return '<meta name="google-site-verification" content="' . $this->settings['googleSiteVerification'] . '" />';
	}
	
	
	# Getter for navigationHierarchy
	public function getNavigationHierarchy ()
	{
		return $this->navigationHierarchy;
	}
	
	
	# Body attributes
	public function bodyAttributes ()
	{
		return pureContent::bodyAttributes (true, 'campl-theme-' . $this->settings['themeColour'] . ($this->isFullWidth ? ' web-application' : '') . ($_SERVER['SERVER_PORT'] == '8080' ? ' editing-mode' : ''));
	}
	
	
	# Body class
	public function bodyClass ()
	{
		return pureContent::bodyAttributesClass ();
	}
	
	
	# Determine whether the user is inside/outside the University's domain
	public function isInternal ()
	{
		return (preg_match ("/{$this->settings['internalDomain']}$/", gethostbyaddr ($_SERVER['REMOTE_ADDR'])));
	}
	
	
	# Determine if the page is the home page
	public function isHomePage ()
	{
		# If the front page is non-curated, do not treat differently
		if (!$this->settings['curatedFrontPage']) {return false;}
		
		# Match against page URL
		return ($_SERVER['SCRIPT_URL'] == '/');
	}
	
	
	# Determine if the page should run in full-width mode
	public function isFullWidth ()
	{
		return $this->isFullWidth;
	}
	
	
	# Function to provide a known user check, obtained from the contacts database
	public function isKnownUser ()
	{
		# End if no API support
		if (!$this->settings['userApi']) {return false;}
		
		# End if not logged in
		if (!isSet ($_SERVER['REMOTE_USER'])) {return false;}
		if (!strlen ($_SERVER['REMOTE_USER'])) {return false;}
		
		# Check the contacts database, returning true if user found (whatever their status), or false if not
		$apiRequest = str_replace ('%username', $_SERVER['REMOTE_USER'], $this->settings['userApi']);
		$context = stream_context_create (array ('http' => array ('header' => 'User-Agent: Site access checker for ' . $_SERVER['SERVER_NAME'])));
		if ($userJson = file_get_contents ($apiRequest, false, $context)) {
			if ($user = json_decode ($userJson, true)) {	// i.e. contains data, rather than being empty
				return $user;
			}
		}
		
		# User not found
		return false;
	}
	
	
	# SSO links - top
	public function ssoLinksTop ()
	{
		return pureContent::ssoLinks ('Raven', false, false, $this->settings['adminUsers'], ".{$this->settings['internalDomain']}$");
	}
	
	
	# SSO links - bottom
	public function ssoLinksBottom ()
	{
		return pureContent::ssoLinks ('Raven');
	}
	
	
	# Edit link - top
	#!# This must be run after ssoLinks - need to change the logic to explicit rather than implicit
	public function editLinkTop ()
	{
		$isKnownUser = $this->isKnownUser ();
		return pureContent::editLink (($isKnownUser && !$isKnownUser['isUndergraduate']), 8080, 'primaryaction right absolute');
	}
	
	
	# Edit link - bottom
	public function editLinkBottom ()
	{
		$isKnownUser = $this->isKnownUser ();
		return pureContent::editLink (($isKnownUser && !$isKnownUser['isUndergraduate']), 8080, 'primaryaction right');
	}
	
	
	# Last updated
	public function lastUpdated ()
	{
		return pureContent::lastUpdated ();
	}
	
	
	# Highlight search terms
	#!# May be possible to remove as Google etc. may not send referrers now, making this pointless CPU execution
	public function highlightSearchTerms ()
	{
		return pureContent::highlightSearchTerms ();
	}
	
	
	# Main menu (along the top)
	public function menu ()
	{
		require_once ('sitetech/menu.html');
		return pureContent::generateMenu ($menu, 'campl-selected', 3, array (), '*', $id = NULL, $class = 'campl-unstyled-list', $returnNotEcho = true, $addSubmenuClass = 'campl-unstyled-list campl-local-dropdown-menu', $submenuDuplicateFirstLink = '<span>&nbsp;&ndash; Overview</span>');
	}
	
	
	# Vertical breadcrumb (along the left side, before the section menu)
	#!# If there is a .menu.html file in the same folder, this is a clear indication the breadcrumb should be extended to that automatically, to avoid needing to set verticalBreadcrumbMaxDepth
	public function verticalBreadcrumb ()
	{
		# Construct the list
		$html = '';
		$navigationHierarchy = $this->getNavigationHierarchy ();
		$navigationHierarchy['/'] = $this->getSiteTitle ();		// Change home to name of site
		$i = 0;
		foreach ($navigationHierarchy as $link => $text) {
			$i++;
			$html .= "\n<li><a href=\"{$link}\">{$text}<span class=\"campl-vertical-breadcrumb-indicator\"></span></a></li>";
			if ($this->settings['verticalBreadcrumbMaxDepth']) {
				if ($i == $this->settings['verticalBreadcrumbMaxDepth']) {break;}
			}
		}
		
		# Return the HTML
		return $html;
	}
	
	
	# Function to find the deepest instance of a file within the current hierarchy, e.g. /foo/bar/zog/ looking for .menu.html could find /foo/bar/.menu.html
	private function deepestInHierarchy ($folderPath, $filename)
	{
		# Assume not found
		$searchFile = false;
		
		# Get array of folders
		$folders = explode ('/', $folderPath);
		array_shift ($folders);
		
		# Search folders from top-level
		$folderStack = array ();
		foreach ($folders as $folder) {
			$folderStack[] = $folder;
			$checkSearchFile = $_SERVER['DOCUMENT_ROOT'] . '/' . implode ('/', $folderStack) . '/' . $filename;
			
			# If the file is found, set as search file; loop will allocate the latest
			if (file_exists ($checkSearchFile)) {
				$searchFile = $checkSearchFile;
			}
		}
		
		# Return the file
		return $searchFile;
	}
	
	
	# Submenu (along the left side)
	public function submenu ()
	{
		# Obtain the menu (if it exists)
		$menufile = $this->getMenufile ();
		
		# Check for a menu file deeper in the hierarchy
		$currentFolder = str_replace ($_SERVER['DOCUMENT_ROOT'], '', $this->currentDirectory);
		if ($searchFile = $this->deepestInHierarchy ($currentFolder, '.menu.html')) {
			$menufile = $searchFile;
		}
		
		# End if not present
		if (!file_exists ($menufile)) {return;}
		
		# If the menu has PHP processing, include it; otherwise treat as HTML
		$menuHtml = file_get_contents ($menufile);
		if (substr_count ($menuHtml, '<?php')) {
			include ($menufile);
		} else {
			$menuHtml = str_replace ('<ul>', '<ul>' . "\n", $menuHtml);
			$menuHtml = str_replace ('<ul>', "<ul class='campl-unstyled-list campl-vertical-breadcrumb-children'>", $menuHtml);
			echo $menuHtml;
		}
	}
	
	
	# Twitter feed
	public function twitterFeed ()
	{
		# Check for file or end
		$twitterFile = $this->currentDirectory . '/.twitter';
		if (!file_exists ($twitterFile)) {return;}
		
		# Get the username in the file
		$twitterHandle = trim (file_get_contents ($twitterFile));
		
		# Render the HTML
		$html  = "\n\t\t\t\t\t\t" . '<br /><br />';
		$html .= "\n\t\t\t\t\t\t" . '<a class="twitter-timeline" data-tweet-limit="5" href="https://twitter.com/' . $twitterHandle . '">@' . $twitterHandle . '</a>';
		$html .= "\n\t\t\t\t\t\t" . '<script async="async" src="//platform.twitter.com/widgets.js" charset="utf-8"></script>';
		
		# Return the HTML
		return $html;
	}
	
	
	# Carousel file
	#!# Should be instead of header.jpg if already assigned
	public function carouselFile ()
	{
		# Determine location
		$carouselFile = $this->currentDirectory . '/header.html';
		
		# Carousel header
		if (!file_exists ($carouselFile)) {return;}
		
		# Echo directly
		include ($carouselFile);
	}
	
	
	# Header menu
	public function headermenu ()
	{
		# End if not present
		if (!file_exists ('./headermenu.html')) {return;}
		
		# Echo directly
		include ('./headermenu.html');
	}
	
	
	# In-page section menu
	public function sectionmenu ()
	{
		# End if not present
		if (!file_exists ('./sectionmenu.html')) {return;}
		
		# Echo directly
		echo '<div class="contextbox">';
		include ('./sectionmenu.html');
		echo '</div>';
	}
	
	
	# Footer menu
	public function footermenu ()
	{
		# End if not present
		if (!file_exists ('./footermenu.html')) {return;}
		
		# Echo directly
		include ('./footermenu.html');
	}
	
	
	# Sidebar
	public function sidebar ()
	{
		# End if not present
		if (!file_exists ($this->currentDirectory . '/sidebar.html')) {return;}
		
		# Echo directly
		echo '<div id="sidebar">';
		include ($this->currentDirectory . '/sidebar.html');
		echo '</div>';
	}
	
	
	# Data sidebar
	public function dataSidebar ()
	{
		# Only run in allowlisted sections, e.g. biography / research project pages
		$show = false;
		foreach ($this->settings['sidebarSections'] as $path) {
			if (preg_match ('/^' . addcslashes ($path, '/') . '/', $_SERVER['SCRIPT_NAME'])) {
				$show = true;
				break;
			}
		}
		if (!$show) {return;}
		
		# Load the auto data sidebar
		require_once ('pureContentLookups.php');
		$pureContentLookups = new pureContentLookups ();
		$html = $pureContentLookups->sidebar ();
		
		# Return the HTML
		return $html;
	}
	
	
	# Head file, to include within the <head>...</head> area
	public function headFile ()
	{
		# End if not present
		#!# Change to .head.html
		if (!file_exists ($this->currentDirectory . '/.header.html')) {return;}
		
		# Echo directly
		include ($this->currentDirectory . '/.header.html');
	}
	
	
	# Body tag file, to include within the <body ...> tag
	#!# No uses remaining so this can be removed
	public function bodyTagFile ()
	{
		# End if not present
		if (!file_exists ($this->currentDirectory . '/.body.html')) {return;}
		
		# Echo directly
		include ($this->currentDirectory . '/.body.html');
	}
	
	
	# DOI autolinking
	#!# Not actually working
	#!# Consider replacing with a JS parser, as the current server-side method involves output buffering
	public function doiAutoLinking ()
	{
		# Do no replacing when in edit mode
		if ($_SERVER['SERVER_PORT'] == '8080') {return;}
		
		# Buffer the output with the callback function
		ob_start (array ($this, 'doiAutoLinkingCallback'));
		
		# Clean up buffer
		ob_get_clean ();
	}
	
	
	# Define the autolinking function; useful list for checking at http://www.crossref.org/01company/15doi_info.html
	private function doiAutoLinkingCallback ($html)
	{
		# Do the autolinking; note that . is probably disallowed at the end, hence the subtle difference in the second match
		return $html = preg_replace ('~\b(doi:(10\.[-a-zA-Z0-9_\/:\(\)\.]+[-a-zA-Z0-9_\/:\(\)]))(\b|\.)~', '<a href="https://doi.org/\\2" target="_blank">\\1</a>', $html);
	}
	
	
	# Shortcode-handled content - run the shortcode to replace the content, echo the modified content, load the append, then end
	public function shortcodeHandledContent ()
	{
		# Define global shortcodes directory
		$additionalDirectory = realpath (dirname (__FILE__)) . '/shortcodes/';
		
		# Run handler
		pureContent::shortcodeHandledContent ($additionalDirectory);
	}
	
	
	# Flowplayer integration
	#!# Need to remove as Flash is now legacy
	public function flowplayer ()
	{
		# End if not present
		if (!substr_count ($this->fileContents, ' class="flv')) {return;}
		
		#!# Hard-coded path
		include ('/sitetech/flowplayer/embed.html');
	}
	
	
	# JS lightbox integration
	public function lightbox ()
	{
		# End if not present
		if (!substr_count ($this->fileContents, ' class="lightbox')) {return;}
		
		#!# Hard-coded path
		include ('/sitetech/jquery/jquery-lightbox/embed.html');
	}
	
	
	# Copyright year
	public function copyrightYear ()
	{
		return date ('Y');
	}
	
	
	# JQuery noconflict
	public function jQueryNoconflict ()
	{
		return "\n" . '<script>$.noConflict();</script>';
	}
	
	
	# Google Analytics
	public function googleAnalytics ()
	{
		# End if not enabled
		if (!$this->settings['googleAnalytics']) {return;}
		
		# Compile and return the HTML
		return $html = '
			<script>
				var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
				document.write (unescape ("%3Cscript src=\'" + gaJsHost + "google-analytics.com/ga.js\'%3E%3C/script%3E"));
			</script>
			<script>
				try {
					var pageTracker = _gat._getTracker ("' . $this->settings['googleAnalytics'] . '");
					pageTracker._trackPageview();
				} catch (err) {}
			</script>
		';
	}
	
	
	# Function to merge the arguments; note that $errors returns the errors by reference and not as a result from the method
	private function assignArguments ($suppliedArguments, $argumentDefaults)
	{
		# Start errors array
		$errors = array ();
		
		# Merge the defaults: ensure that arguments with a non-null default value are set (throwing an error if not), or assign the default value if none is specified
		$arguments = array ();
		foreach ($argumentDefaults as $argument => $defaultValue) {
			if (is_null ($defaultValue)) {
				if (!isSet ($suppliedArguments[$argument])) {
					$errors['absent' . ucfirst ($argument)] = "No '<strong>{$argument}</strong>' has been set.";
					$arguments[$argument] = $defaultValue;
				} else {
					$arguments[$argument] = $suppliedArguments[$argument];
				}
				
			# Otherwise assign argument as normal
			} else {
				$arguments[$argument] = (isSet ($suppliedArguments[$argument]) ? $suppliedArguments[$argument] : $defaultValue);
			}
		}
		
		# Handle the errors directly if required if any arise
		if ($errors) {
			echo print_r ($errors);
			return false;
		}
		
		# Return the arguments
		return $arguments;
	}
}

?>
