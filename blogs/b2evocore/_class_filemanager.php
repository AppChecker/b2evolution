<?php
/**
 * The Filemanager class.
 *
 * b2evolution - {@link http://b2evolution.net/}
 * Released under GNU GPL License - {@link http://b2evolution.net/about/license.html}
 * @copyright (c)2003-2004 by Francois PLANQUE - {@link http://fplanque.net/}
 *
 * @package admin
 *
 * @todo: Permissions!
 * @todo: favorite folders/bookmarks
 */
if( !defined('DB_USER') ) die( 'Please, do not access this page directly.' );

/**
 * Includes
 */
require_once( dirname(__FILE__).'/_functions_files.php' );


/**
 * TODO: docblock for class
 */
class FileManager
{
	/**
	 * root (like user_X or blog_X), defaults to current user's dir (#)
	 * @param string
	 */
	var $root;

	/**
	 * root directory
	 * @param string
	 */
	var $root_dir;

	/**
	 * root URL
	 * @param string
	 */
	var $root_url;

	/**
	 * current working directory
	 * @param string
	 */
	var $cwd;


	/**
	 * User preference: sort dirs at top
	 * @var boolean
	 */
	var $dirsattop = true;

	/**
	 * User preference: show hidden files?
	 * @var boolean
	 */
	var $showhidden = true;

	/**
	 * User preference: show permissions like "ls -l" (true) or octal (false)?
	 * @var boolean
	 */
	var $permlikelsl = true;

	/**
	 * User preference: recursive size of dirs?
	 * @todo needs special permission (server load!)
	 * @var boolean
	 */
	var $fulldirsize = false;

	// --- going to user options ---
	var $default_chmod_file = 0700;
	var $default_chmod_dir = 0700;


	/* ----- PRIVATE ----- */

	/**
	 * the current index of the directory items (looping)
	 * @var integer
	 * @access protected
	 */
	var $current_idx = -1;


	/**
	 * order files by what? (name/type/size/lastm/perms)
	 * 'name' as default.
	 * @var string
	 * @access protected
	 */
	var $order = '#';

	/**
	 * files ordered ascending?
	 * '#' is default and means ascending for 'name', descending for the rest
	 * @var boolean
	 * @access protected
	 */
	var $orderasc = '#';

	/**
	 * relative path
	 * @var string
	 * @access protected
	 */
	var $path = '';


	/**
	 * Constructor
	 *
	 * @param User the current User {@link User}}
	 * @param string the root directory ('user' => user's dir)
	 * @param string the URL where the object is included (for generating links)
	 * @param string the dir of the Filemanager object (relative to root)
	 * @param string filter files by what?
	 * @param boolean is the filter a regular expression (default is glob pattern)
	 * @param string order files by what? ('#' means 'name')
	 * @param boolean order ascending or descending? '#' means ascending for 'name', descending for other
	 */
	function FileManager( &$cUser, $url, $root = '#', $path = '', $filter = '#', $filter_regexp = '#', $order = '#', $asc = '#' )
	{
		global $basepath, $baseurl, $media_subdir, $core_dirout, $admin_subdir, $admin_url;
		global $BlogCache;

		$this->entries = array();  // the directory entries
		$this->Messages = new Log( 'error' );

		$this->User =& $cUser;

		$this->filter = $filter;
		$this->filter_regexp = $filter_regexp;
		if( $this->filter_regexp && !isRegexp( $this->filter ) )
		{
			$this->Messages->add( sprintf( T_('The filter [%s] is not a regular expression.'), $this->filter ) );
			$this->filter = '.*';
		}

		$this->order = ( in_array( $order, array( 'name', 'type', 'size', 'lastm', 'perms' ) ) ? $order : '#' );
		$this->orderasc = ( $asc == '#' ? '#' : (bool)$asc );

		$this->loadSettings();

		// base URL, used for created links
		$this->url = $url;

		// path/url for images (icons)
		$this->imgpath = $basepath.$admin_subdir.'img/fileicons/';
		$this->imgurl = $admin_url.'img/fileicons/';

		// TODO: get user's/group's root


		// -- get/translate root directory ----
		$this->root = $root;

		$root_A = explode( '_', $this->root );

		if( $this->User->login == 'demouser' )
		{
			$this->root_dir = $basepath.'media_test/';
			$this->root_url = $baseurl.'media_test/';
		}
		elseif( count( $root_A ) == 2 )
		{
			switch( $root_A[0] )
			{
				case 'blog':
					$Blog = $BlogCache->get_by_ID( $root_A[1] );
					$this->root_dir = $Blog->get( 'mediadir' );
					$this->root_url = $Blog->get( 'mediaurl' );
					break;
			}
		}
		else switch( $root_A[0] )
		{
			case '#':
			case 'user':
				$this->root_dir = $this->User->get( 'fm_rootdir' );
				$this->root_url = $this->User->get( 'fm_rooturl' );
				break;

			default:  // straight path
				$this->root_dir = trailing_slash( $root );
		}

		$this->debug( $this->root, 'root' );
		$this->debug( $this->root_dir, 'root_dir' );
		$this->debug( $this->root_url, 'root_url' );

		$this->cwd = trailing_slash( $this->root_dir.$path );
		$this->debug( $this->cwd, 'cwd' );

		// get real cwd
		$realpath = realpath($this->cwd);
		$this->debug( $realpath, 'realpath()' );

		if( !$realpath )
		{ // does not exist
			$this->cwd = $this->root_dir;
		}
		else
		{
			$realpath = trailing_slash( str_replace( '\\', '/', $realpath ) );

			if( !preg_match( '#^'.$this->root_dir.'#', $realpath ) )
			{ // cwd is not below root!
				$this->Messages->add( T_( 'You are not allowed to go outside your root directory!' ) );
				$this->cwd = $this->root_dir;
			}
			else
			{ // allowed
				$this->cwd = $realpath;
			}
		}

		// get the subpath relative to root
		$this->path = preg_replace( '#^'.$this->root_dir.'#', '', $this->cwd );
		$this->debug( $this->path, 'path' );


		$this->loadentries();

		// load file icons..
		require( $core_dirout.$admin_subdir.'img/fileicons/fileicons.php' );

		/**
		 * These are the filetypes. The extension is a regular expression that must match the end of the file.
		 */
		$this->filetypes = array(
			'.ai' => T_('Adobe illustrator'),
			'.bmp' => T_('Bmp image'),
			'.bz'  => T_('Bz Archive'),
			'.c' => T_('Source C '),
			'.cgi' => T_('CGI file'),
			'.conf' => T_('Config file'),
			'.cpp' => T_('Source C++'),
			'.css' => T_('Stylesheet'),
			'.exe' => T_('Executable'),
			'.gif' => T_('Gif image'),
			'.gz'  => T_('Gz Archive'),
			'.h' => T_('Header file'),
			'.hlp' => T_('Help file'),
			'.htaccess' => T_('Apache file'),
			'.htm' => T_('Hyper text'),
			'.html' => T_('Hyper text'),
			'.htt' => T_('Windows access'),
			'.inc' => T_('Include file'),
			'.inf' => T_('Config File'),
			'.ini' => T_('Setting file'),
			'.jpe?g' => T_('Jpeg Image'),
			'.js'  => T_('JavaScript'),
			'.log' => T_('Log file'),
			'.mdb' => T_('Access DB'),
			'.midi' => T_('Media file'),
			'.php' => T_('PHP script'),
			'.phtml' => T_('php file'),
			'.pl' => T_('Perl script'),
			'.png' => T_('Png image'),
			'.ppt' => T_('MS Power point'),
			'.psd' => T_('Photoshop Image'),
			'.ra' => T_('Real file'),
			'.ram' => T_('Real file'),
			'.rar' => T_('Rar Archive'),
			'.sql' => T_('SQL file'),
			'.te?xt' => T_('Text document'),
			'.tgz' => T_('Tar gz archive'),
			'.vbs' => T_('MS Vb script'),
			'.wri' => T_('Document'),
			'.xml' => T_('XML file'),
			'.zip' => T_('Zip Archive'),
			);

		$this->sort();

		$this->restart();

		$this->debug( $this->entries, 'entries' );
	}


	function loadentries()
	{
		if( $this->filter == '#' || $this->filter_regexp )
		{
			$dir = @dir( $this->cwd );
			$diropened = (bool)$dir;
		}
		else
		{
			$oldcwd = getcwd();
			$diropened = @chdir( $this->cwd );
			$dir = glob( $this->filter, GLOB_BRACE ); // GLOB_BRACE allows {a,b,c} to match a, b or c
			chdir( $oldcwd );
		}

		if( !$diropened )
		{
			$this->Messages->add( sprintf( T_('Cannot open directory [%s]!'), $this->cwd ) );
			return false;
		}
		else
		{ // read the directory
			if( $dir === false )
			{ // glob-$dir is empty/false
				return false;
			}
			$i = 0;
			while( ( ($this->filter == '#' || $this->filter_regexp) && ($entry = $dir->read()) )
						|| ($this->filter != '#' && !$this->filter_regexp && ( $entry = each( $dir ) ) && ( $entry = $entry[1] ) ) )
			{
				if( $entry == '.' || $entry == '..'
						|| ( !$this->showhidden && substr($entry, 0, 1) == '.' )  // hidden files (prefixed with .)
						|| ( $this->filter != '#' && $this->filter_regexp && !preg_match( '#'.str_replace( '#', '\#', $this->filter ).'#', $entry ) ) // does not match the regexp filter
					)
				{ // don't use these
					continue;
				}

				$i++;

				$this->entries[ $i ]['name'] = $entry;
				if( is_dir( $this->cwd.'/'.$entry ) )
				{
					$this->entries[ $i ]['type'] = 'dir';
					if( $this->fulldirsize )
						$this->entries[ $i ]['size'] = get_dirsize_recursive( $this->cwd.'/'.$entry );
					else $this->entries[ $i ]['size'] = false;
				}
				else
				{
					$this->entries[ $i ]['type'] = 'file';
					$this->entries[ $i ]['size'] = filesize( $this->cwd.$entry );
				}

				$this->entries[ $i ]['lastm'] = filemtime( $this->cwd.$entry );
				$this->entries[ $i ]['perms'] = fileperms( $this->cwd.$entry );

			}
			if( $this->filter_regexp )
			{
				$dir->close();
			}

		}
	}


	/**
	 * get the current url, with all relevant GET params (cd, order, asc)
	 *
	 * @param string override root (blog_X or user_X)
	 * @param string override cd
	 * @param string override order
	 * @param integer override asc
	 */
	function curl( $root = '#', $path = '#', $filter = '#', $filter_regexp = '#', $order = '#', $orderasc = '#' )
	{
		$r = $this->url;

		foreach( array('root', 'path', 'filter', 'filter_regexp', 'order', 'orderasc') as $check )
		{
			if( $$check === false )
			{ // don't include
				continue;
			}
			if( $$check != '#' )
			{ // use local param
				$r = url_add_param( $r, $check.'='.$$check );
			}
			elseif( $this->$check != '#' )
			{
				$r = url_add_param( $r, $check.'='.$this->$check );
			}
		}

		return $r;
	}


	/**
	 * generates hidden input fields for forms, based on {@link curl()}}
	 */
	function form_hiddeninputs( $root = '#', $path = '#', $filter = '#', $filter_regexp = '#', $order = '#', $asc = '#' )
	{
		// get curl(), remove leading URL and '?'
		$params = preg_split( '/&amp;/', substr( $this->curl( $root, $path, $filter, $filter_regexp, $order, $asc ), strlen( $this->url )+1 ) );

		$r = '';
		foreach( $params as $lparam )
		{
			if( $pos = strpos($lparam, '=') )
			{
				$r .= '<input type="hidden" name="'.substr( $lparam, 0, $pos ).'" value="'.format_to_output( substr( $lparam, $pos+1 ), 'formvalue' ).'" />';
			}
		}

		return $r;
	}


	/**
	 * get an array of available root directories
	 * @return array of arrays for each root: array( type [blog/user], id, name )
	 */
	function get_roots()
	{
		global $BlogCache;

		$bloglist = $BlogCache->load_user_blogs( 'browse', $this->User->ID );

		$r = array();

		foreach( $bloglist as $blog_ID )
		{
			$Blog = & $BlogCache->get_by_ID( $blog_ID );

			$r[] = array( 'type' => 'blog', 'id' => $blog_ID, 'name' => $Blog->get( 'shortname' ) );
		}

		return $r;
	}


	/**
	 * return the current filter
	 *
	 * @param boolean add a note when it's a regexp?
	 * @return string the filter
	 */
	function get_filter( $note = true )
	{
		if( $this->filter == '#' )
		{
			return T_('no filter');
		}
		else
		{
			$r = $this->filter;
			if( $note && $this->filter_regexp )
			{
				$r .= ' ('.T_('regular expression').')';
			}
			return $r;
		}
	}


	/**
	 * @return integer 1 for ascending sorting, 0 for descending
	 */
	function is_sortingasc( $type = '' )
	{
		if( empty($type) )
			$type = $this->order;

		if( $this->orderasc == '#' )
		{ // default
			return ( $type == 'name' ) ? 1 : 0;
		}
		else
		{
			return ( $this->orderasc ) ? 1 : 0;
		}
	}


	/**
	 * is a filter active?
	 * @return boolean
	 */
	function is_filtering()
	{
		return $this->filter !== '#';
	}


	function sortlink( $type )
	{
		$r = $this->curl( '#', '#', '#', '#', $type, false );

		if( $this->order == $type )
		{ // change asc
			$r .= '&amp;asc='.(1 - $this->is_sortingasc());
		}

		return $r;
	}


	function link_sort( $type, $atext )
	{
		$r = '<a href="'.$this->sortlink( $type ).'" title="'
		.( ($this->order == $type && !$this->is_sortingasc($type))
				||( $this->order != $type
						&& $this->is_sortingasc($type))
							? T_('sort ascending by this column') : T_('sort descending by this column'))
		.'">'.$atext.'</a>';

		if( $this->order == $type )
		{
			if( $this->is_sortingasc() )
				$r .= ' '.$this->icon( 'ascending', 'imgtag' );
			else
				$r .= ' '.$this->icon( 'descending', 'imgtag' );
		}

		return $r;
	}


	/**
	 * translates $asc parameter, if it's '#'
	 * @param boolean sort ascending?
	 * @return integer 1 for ascending, 0 for descending
	 */
	function translate_asc( $asc, $order )
	{
		if( $asc != '#' )
		{
			return $asc;
		}
		elseif( $this->orderasc != '#' )
		{
			return $this->orderasc;
		}
		else
		{
			return ($order == 'name') ? 1 : 0;
		}
	}


	/**
	 * translates $order parameter, if it's '#'
	 * @param string order by?
	 * @return string order by what?
	 */
	function translate_order( $order )
	{
		if( $order != '#' )
		{
			return $order;
		}
		elseif( $this->order != '#' )
		{
			return $this->order;
		}
		else
		{
			return 'name';
		}
	}


	/**
	 * sorts the entries.
	 *
	 * @param string the entries key
	 * @param boolean ascending (true) or descending
	 */
	function sort( $order = '#', $asc = '#' )
	{
		if( !$this->entries )
		{
			return false;
		}

		$order = $this->translate_order( $order );
		$asc = $this->translate_asc( $asc, $order );

		if( $order == 'size' )
		{
			if( $this->fulldirsize )
			{
				$sortfunction = '$r = ( $a[\'size\'] - $b[\'size\'] );';
			}
			else
			{
				$sortfunction = '$r = ($a[\'type\'].$b[\'type\'] == \'dirdir\') ?
															strcasecmp( $a[\'name\'], $b[\'name\'] )
															: ( $a[\'size\'] - $b[\'size\'] );';
			}
		}
		elseif( $order == 'type' )
		{ // stupid dirty hack: copy the whole Filemanager into global array to access filetypes // TODO: optimize
			global $typetemp;
			$typetemp = $this;
			$sortfunction = 'global $typetemp; $r = strcasecmp( $typetemp->cget_file($a[\'name\'], \'type\'), $typetemp->cget_file($b[\'name\'], \'type\') );';
		}
		else
			$sortfunction = '$r = strcasecmp( $a["'.$order.'"], $b["'.$order.'"] );';

		if( !$asc )
		{ // switch order
			$sortfunction .= '$r = -$r;';
		}

		if( $this->dirsattop )
			$sortfunction .= 'if( $a[\'type\'] == \'dir\' && $b[\'type\'] != \'dir\' )
													$r = -1;
												elseif( $b[\'type\'] == \'dir\' && $a[\'type\'] != \'dir\' )
													$r = 1;';
		$sortfunction .= 'return $r;';

		#echo $sortfunction;
		usort( $this->entries, create_function( '$a, $b', $sortfunction ) );
	}


	/**
	 * go to next entry
	 *
	 * @param string can be used to query only 'file's or 'dir's.
	 * @return boolean true on success, false on end of list
	 */
	function next( $type = '' )
	{
		$this->current_idx++;
		if( !$this->entries || $this->current_idx >= count( $this->entries ) )
		{
			return false;
		}

		if( $type != '' )
		{
			if( $type == 'dir' && $this->entries[ $this->current_idx ]['type'] != 'dir' )
			{ // we want a dir
				return $this->next( 'dir' );
			}
			elseif( $this->entries[ $this->current_idx ]['type'] != 'file' )
			{
				return $this->next( 'file' );
			}
		}
		else
		{
			$this->current_entry = $this->entries[ $this->current_idx ];
			return true;
		}
	}


	/**
	 * Displays file permissions like 'ls -l'
	 *
	 * @author zilinex at linuxmail dot com {@link www.php.net/manual/en/function.fileperms.php}
	 * @todo move out of class
	 * @param string
	 */
	function translatePerm( $in_Perms )
	{
		$sP = '';

		if(($in_Perms & 0xC000) == 0xC000)		 // Socket
			$sP = 's';
		elseif(($in_Perms & 0xA000) == 0xA000) // Symbolic Link
			$sP = 'l';
		elseif(($in_Perms & 0x8000) == 0x8000) // Regular
			$sP = '&minus;';
		elseif(($in_Perms & 0x6000) == 0x6000) // Block special
			$sP = 'b';
		elseif(($in_Perms & 0x4000) == 0x4000) // Directory
			$sP = 'd';
		elseif(($in_Perms & 0x2000) == 0x2000) // Character special
			$sP = 'c';
		elseif(($in_Perms & 0x1000) == 0x1000) // FIFO pipe
			$sP = 'p';
		else												 // UNKNOWN
			$sP = 'u';

		// owner
		$sP .= (($in_Perms & 0x0100) ? 'r' : '&minus;') .
						(($in_Perms & 0x0080) ? 'w' : '&minus;') .
						(($in_Perms & 0x0040) ? (($in_Perms & 0x0800) ? 's' : 'x' ) :
																		(($in_Perms & 0x0800) ? 'S' : '&minus;'));

		// group
		$sP .= (($in_Perms & 0x0020) ? 'r' : '&minus;') .
						(($in_Perms & 0x0010) ? 'w' : '&minus;') .
						(($in_Perms & 0x0008) ? (($in_Perms & 0x0400) ? 's' : 'x' ) :
																		(($in_Perms & 0x0400) ? 'S' : '&minus;'));

		// world
		$sP .= (($in_Perms & 0x0004) ? 'r' : '&minus;') .
						(($in_Perms & 0x0002) ? 'w' : '&minus;') .
						(($in_Perms & 0x0001) ? (($in_Perms & 0x0200) ? 't' : 'x' ) :
																		(($in_Perms & 0x0200) ? 'T' : '&minus;'));
		return $sP;
	}


	/**
	 * Get an attribute of the current entry,
	 *
	 * @param string property
	 * @param string optional parameter
	 * @param string gets through sprintf where %s gets replaced with the result
	 */
	function cget( $what, $param = '', $displayiftrue = '' )
	{
		$path = isset($this->current_entry) ? $this->cwd.'/'.$this->current_entry['name'] : false;

		/* // detect dying loops
		global $owhat;
		if( $what == $owhat )
		{
			pre_dump( $what, 'loop' );
			return;
		}
		$owhat = $what;*/


		switch( $what )
		{
			case 'path':
				$r = $path;
				break;

			case 'url':
				$r = $this->root_url.$this->path.$this->current_entry['name'];
				break;

			case 'ext':  // the file extension
				if( preg_match('/\.([^.])+$/', $this->current_entry['name'], $match) )
					$r = $match[1];
				else
					$r = false;
				break;

			case 'perms':
				if( $param != 'octal'
						&& ($this->permlikelsl || $param == 'lsl') )
					$r = $this->translatePerm( $this->current_entry['perms'] );
				else
					$r = substr( sprintf('%o', $this->current_entry['perms']), -3 );
				break;

			case 'nicesize':
				if( $this->cisdir() && !$this->fulldirsize )
					$r = /* TRANS: short for '<directory>' */ T_('&lt;dir&gt;');
				elseif( ($r = $this->cget('size')) !== false )
					$r = bytesreadable( $r );
				else
					$r = '';
				break;

			case 'imgsize':
				$r = imgsize( $path, $param );
				break;

			case 'link':
				if( $param == 'parent' )
				{ // TODO: check if allowed
					$r = $this->curl( '#', $this->path.'..' );
				}
				elseif( $param == 'home' )
				{
					$r = $this->url;
				}
				elseif( $this->current_entry['type'] == 'dir' && $param != 'forcefile' )
				{
					$r = $this->curl( '#', $this->path.$this->current_entry['name'] );
				}
				else
				{
					$r = $this->curl( '#', $this->path ).'&amp;file='.urlencode($this->current_entry['name']);
				}
				break;

			case 'link_edit':
				if( $this->current_entry['type'] == 'dir' ) $r = false;
				else $r = $this->cget('link').'&amp;action=edit';
				break;

			case 'link_copymove':
				if( $this->current_entry['type'] == 'dir' ) $r = false;
				else $r = $this->cget('link').'&amp;action=copymove';
				break;

			case 'link_rename':
				$r = $this->cget('link', 'forcefile').'&amp;action=rename';
				break;

			case 'link_delete':
				$r = $this->cget('link', 'forcefile').'&amp;action=delete';
				break;

			case 'link_editperm':
				$r = $this->cget('link', 'forcefile').'&amp;action=editperm';
				break;

			case 'lastmod':
				$r = date_i18n( locale_datefmt().' '.locale_timefmt(), $this->current_entry['lastm'] );
				break;

			case 'type':
				$r = $this->type( 'cfile' );
				break;

			case 'iconfile':
				$r = $this->icon( 'cfile', 'file' );
				break;

			case 'iconurl':
				$r = $this->icon( 'cfile', 'url' );
				break;

			case 'iconsize':
				$r = $this->icon( 'cfile', 'size', $param );
				break;

			case 'iconimg':
				$r = $this->icon( 'cfile', 'imgtag', $param );
				break;

			default:
				$r = ( isset( $this->current_entry[ $what ] ) ) ? $this->current_entry[ $what ] : false;
				break;
		}
		if( $r && !empty($displayiftrue) )
		{
			return sprintf( $displayiftrue, $r );
		}
		else
			return $r;
	}


	/**
	 * wrapper for cget() to display right away
	 * @param string property of loop file
	 * @param mixed optional parameter
	 */
	function cdisp( $what, $param = '', $displayiftrue = '' )
	{
		if( ( $r = $this->cget( $what, $param, $displayiftrue ) ) !== false )
		{
			echo $r;
			return true;
		}
		return $r;
	}


	/**
	 * wrapper for cget_file() to display right away
	 * @param string the file
	 * @param string property of loop file
	 * @param mixed optional parameter
	 */
	function cdisp_file( $file, $what, $param = '', $displayiftrue = '' )
	{
		if( ( $r = $this->cget_file( $file, $what, $param, $displayiftrue ) ) !== false )
		{
			echo $r;
		}
		return $r;
	}


	/**
	 * is the current file a directory?
	 *
	 * @param string force a specific file
	 * @return boolean true if yes, false if not
	 */
	function cisdir( $file = '' )
	{
		if( $file != '' )
		{
			if( $this->loadc( $file ) )
			{
				$isdir = ($this->current_entry['type'] == 'dir');
				$this->restorec();
				return $isdir;
			}
			else return false;
		}
		return ($this->current_entry['type'] == 'dir');
	}


	/**
	 * get properties of a special icon
	 *
	 * @param string icon for what (special puposes or 'cfile' for current file/dir)
	 * @param string what to return for that icon (file, url, size {@link see imgsize()}})
	 * @param string additional parameter (for size)
	 */
	function icon( $for, $what = 'imgtag', $param = '' )
	{
		if( $for == 'cfile' )
		{
			if( !isset($this->current_entry) )
				$iconfile = false;
			elseif( $this->current_entry['type'] == 'dir' )
				$iconfile = $this->fileicons_special['folder'];
			else
			{
				$iconfile = $this->fileicons_special['unknown'];
				foreach( $this->fileicons as $ext => $imgfile )
				{
					if( preg_match( '/'.$ext.'$/i', $this->current_entry['name'], $match ) )
					{
						$iconfile = $imgfile;
						break;
					}
				}
			}
		}
		elseif( isset( $this->fileicons_special[$for] ) )
		{
			$iconfile = $this->fileicons_special[$for];
		}
		else $iconfile = false;

		if( !$iconfile || !file_exists( $this->imgpath.$iconfile ) )
		{
			#return false;
			return '<span class="small">[no image for '.$for.'!]</small>';
		}

		switch( $what )
		{
			case 'file':
				$r = $iconfile;
				break;

			case 'url':
				$r = $this->imgurl.$iconfile;
				break;

			case 'size':
				$r = imgsize( $this->imgpath.$iconfile, $param );
				break;

			case 'imgtag':
				$r = '<img class="middle" src="'.$this->icon( $for, 'url' ).'" '.$this->icon( $for, 'size', 'string' )
				.' alt="';

				if( $for == 'cfile' )
				{ // extension as alt-tag for cfile-icons
					$r .= $this->cget( 'ext' );
				}

				$r .= '" title="'.$this->type( $for );

				$r .= '" />';
				break;


			default:
				echo 'unknown what: '.$what;
		}

		return $r;
	}


	function type( $param )
	{
		if( $param == 'cfile' )
		{
			if( !isset($this->current_entry) )
				$r = false;
			elseif( $this->current_entry['type'] == 'dir' )
				$r = T_('directory');
			else
			{
				$found = false;
				foreach( $this->filetypes as $type => $desc )
				{
					if( preg_match('/'.$type.'$/i', $this->current_entry['name']) )
					{
						$r = $desc;
						$found = true;
						break;
					}
				}
				if( !$found ) $r = T_('unknown');
			}
		}
		elseif( $param == 'parent' )
			$r = T_('go to parent directory');
		elseif( $param == 'home' )
			$r = T_('home directory');
		elseif( $param == 'descending' )
			$r = T_('descending');
		elseif( $param == 'ascending' )
			$r = T_('ascending');
		elseif( $param == 'edit' )
			$r = T_('Edit');
		elseif( $param == 'copymove' )
			$r = T_('Copy/Move');
		elseif( $param == 'rename' )
			$r = T_('Rename');
		elseif( $param == 'delete' )
			$r = T_('Delete');
		elseif( $param == 'window_new' )
			$r = T_('Open in new window');
		else $r = false;

		return $r;
	}


	/**
	 * loads a specific file as current file as saves current one (can be nested).
	 *
	 * (for restoring see {@link Fileman::restorec()})
	 *
	 * @param string the filename (in cwd)
	 * @return boolean true on success, false on failure.
	 */
	function loadc( $file )
	{
		$this->save_idx[] = $this->current_idx;

		if( ($this->current_idx = $this->findkey( $file )) === false )
		{ // file could not be found
			$this->current_idx = array_pop( $this->save_idx );
			return false;
		}
		else
		{
			$this->current_entry = $this->entries[ $this->current_idx ];
			return true;
		}
	}


	/**
	 * restores the previous current entry (see {@link Fileman::loadc()})
	 * @return boolean true on success, false on failure (if there are no entries to restore on the stack)
	 */
	function restorec()
	{
		if( count($this->save_idx) )
		{
			$this->current_idx = array_pop( $this->save_idx );
			if( $this->current_idx != -1 )
			{
				$this->current_entry = $this->entries[ $this->current_idx ];
			}
			return true;
		}
		else
		{
			return false;
		}
	}


	/**
	 * wrapper to get properties of a specific file.
	 *
	 * @param string the file (in cwd)
	 * @param string what to get
	 * @param mixed optional parameter
	 */
	function cget_file( $file, $what, $param = '', $displayiftrue = '' )
	{
		if( $this->loadc( $file ) )
		{
			$r = $this->cget( $what, $param, $displayiftrue );
		}
		else
		{
			return false;
		}

		$this->restorec();
		return $r;
	}



	/**
	 * do actions to a file/dir
	 *
	 * @param string filename (in cwd)
	 * @param string the action (chmod)
	 * @param string parameter for action
	 */
	function cdo_file( $filename, $what, $param = '' )
	{
		if( $this->loadc( $filename ) )
		{
			$path = $this->cget( 'path' );
			switch( $what )
			{
				case 'chmod':
					if( !file_exists($path) )
					{
						$this->Messages->add( sprintf(T_('File [%s] does not exists.'), $filename) );
					}
					else
					{
						$oldperm = $this->cget( 'perms' );
						if( chmod( $path, decoct($param) ) )
						{
							clearstatcache();
							// update current entry
							$this->entries[ $this->current_idx ]['perms'] = fileperms( $path );
							$this->current_entry['perms'] = fileperms( $path );
							$r = true;
						}
						if( $oldperm != $this->cget( 'perms' ) )
						{
							$this->Messages->add( sprintf( T_('Changed permissions for [%s] to %s.'), $filename, $this->cget( 'perms' ) ), 'note' );
						}
						else
						{
							$this->Messages->add( sprintf( T_('Permissions for [%s] not changed.'), $filename ) );
						}
					}
					break;

				case 'send':
					if( is_dir($path) )
					{ // we cannot send directories!
						return false;
					}
					else
					{
						header('Content-type: application/octet-stream');
						//force download dialog
						header('Content-disposition: attachment; filename="' . $filename . '"');

						header('Content-transfer-encoding: binary');
						header('Content-length: ' . filesize($path));

						//send file contents
						readfile($path);
						exit;
					}
			}
		}
		else
		{
			$this->Messages->add( sprintf( T_('File [%s] not found.'), $filename ) );
			return false;
		}

		$this->restorec();
		return $r;
	}


	/**
	 *
	 */
	function checkstatus( $path )
	{
	}

	/**
	 * restart
	 */
	function restart()
	{
		$this->current_idx = -1;
	}


	/**
	 * get an array list of a specific type
	 *
	 * @param string type ('dirs' or 'files', '' means all)
	 * @param return array
	 */
	function arraylist( $type = '' )
	{
		$r = array();
		foreach( $this->entries as $entry )
		{
			if( $type == ''
					|| ( $type == 'files' && $entry['type'] != 'dir' )
					|| ( $type == 'dirs' && $entry['type'] == 'dir' )
				)
			{
				$r[] = $entry['name'];
			}
		}
		return $r;
	}


	/**
	 * Remove a file or directory.
	 *
	 * @param string filename, defaults to current loop entry
	 * @param boolean delete subdirs of a dir?
	 * @return boolean true on success, false on failure
	 */
	function delete( $file = '#', $delsubdirs = false )
	{
		// TODO: permission check

		if( $file == '#' )
		{ // use current entry
			if( isset($this->current_entry) )
			{
				$entry = $this->current_entry;
			}
			else
			{
				$this->Messages->add('delete: no current file!');
				return false;
			}
		}
		else
		{ // use a specific entry
			if( ($key = $this->findkey( $file )) !== false )
			{
				$entry = $this->entries[$key];
			}
			else
			{
				$this->Messages->add( sprintf(T_('File [%s] not found.'), $file) );
				return false;
			}
		}

		if( $entry['type'] == 'dir' )
		{
			if( $delsubdirs )
			{
				if( deldir_recursive( $this->cwd.'/'.$entry['name'] ) )
				{
					$this->Messages->add( sprintf( T_('Directory [%s] and subdirectories deleted.'), $entry['name'] ), 'note' );
					return true;
				}
				else
				{
					$this->Messages->add( sprintf( T_('Directory [%s] could not be deleted.'), $entry['name'] ) );
					return false;
				}
			}
			elseif( @rmdir( $this->cwd.'/'.$entry['name'] ) )
			{
				$this->Messages->add( sprintf( T_('Directory [%s] deleted.'), $entry['name'] ), 'note' );
				return true;
			}
			else
			{
				$this->Messages->add( sprintf( T_('Directory [%s] could not be deleted (probably not empty).'), $entry['name'] ) );
				return false;
			}
		}
		else
		{
			if( unlink( $this->cwd.'/'.$entry['name'] ) )
			{
				$this->Messages->add( sprintf( T_('File [%s] deleted.'), $entry['name'] ), 'note' );
				return true;
			}
			else
			{
				$this->Messages->add( sprintf( T_('File [%s] could not be deleted.'), $entry['name'] ) );
				return false;
			}
		}
	}


	/**
	 * Create a root dir, while making the suggested name an safe filename.
	 * @param string the path where to create the directory
	 * @param string suggested dirname, will be converted to a safe dirname
	 * @param integer permissions for the new directory (octal format)
	 * @return mixed full path that has been created; false on error
	 */
	function create_rootdir( $path, $suggested_name, $chmod = '#' )
	{
		$realname = safefilename( $suggested_name );
		if( $this->createdir( $realname, $path, $chmod ) )
		{
			return $path.'/'.$realname;
		}
		else
		{
			return false;
		}
	}


	/**
	 * Create a directory.
	 * @param string the name of the directory
	 * @param string path to create the directory in (default is cwd)
	 * @param integer permissions for the new directory (octal format)
	 * @return boolean true on success, false on failure
	 */
	function createdir( $dirname, $path = '#', $chmod = '#' )
	{
		if( $path == '#' )
		{
			$path = $this->cwd;
		}
		if( $chmod == '#' )
		{
			$chmod = $this->default_chmod_dir;
		}
		if( empty($dirname) )
		{
			$this->Messages->add( T_('Cannot create empty directory.') );
			return false;
		}
		elseif( !mkdir( $path.'/'.$dirname, $chmod ) )
		{
			$this->Messages->add( sprintf( T_('Could not create directory [%s] in [%s].'), $dirname, $path ) );
			return false;
		}

		$this->Messages->add( sprintf( T_('Directory [%s] created.'), $dirname ), 'note' );
		return true;
	}


	/**
	 * Create a file
	 * @param string filename
	 * @param integer permissions for the new file (octal format)
	 */
	function createfile( $filename, $chmod = '#' )
	{
		$path = $this->cwd.'/'.$filename;

		if( $chmod == '#' )
		{
			$chmod = $this->default_chmod_file;
		}

		if( empty($filename) )
		{
			$this->Messages->add( T_('Cannot create empty file.') );
			return false;
		}
		elseif( file_exists($path) )
		{
			// TODO: allow overwriting
			$this->Messages->add( sprintf(T_('File [%s] already exists.'), $filename) );
			return false;
		}
		elseif( !touch( $path ) )
		{
			$this->chmod( $filename, $chmod );
			$this->Messages->add( sprintf( T_('Could not create file [%s] in [%s].'), $filename, $this->cwd ) );
			return false;
		}
		else
		{
			$this->Messages->add( sprintf( T_('File [%s] created.'), $filename ), 'note' );
			return true;
		}
	}


	/**
	 * Reloads the page where Filemanager was called for, useful when a file or dir has been created.
	 */
	function reloadpage()
	{
		header( 'Location: '.$this->curl() );
		exit;
	}


	/**
	 * finds an entry ('name' field) in the entries array
	 *
	 * @param string needle
	 * @return integer the key of the entries array
	 */
	function findkey( $find )
	{
		foreach( $this->entries as $key => $arr )
		{
			if( $arr['name'] == $find )
			{
				return $key;
			}
		}
		return false;
	}


	function debug( $what, $desc, $forceoutput = 0 )
	{
		global $Debuglog;

		ob_start();
		pre_dump( $what, '[Fileman] '.$desc );
		$Debuglog->add( ob_get_contents() );
		if( $forceoutput )
			ob_end_flush();
		else
			ob_end_clean();
	}


	/**
	 * returns cwd, where the accessible directories (below root)  are clickable
	 * @return string cwd as clickable html
	 */
	function cwd_clickable()
	{
		// get the part that is clickable

		$pos_lastslash = strrpos( $this->root_dir, '/' );
		$r = substr( $this->root_dir, 0, $pos_lastslash );

		$clickabledirs = explode( '/', substr( $this->cwd, $pos_lastslash+1 ) );

		$cd = '/';
		foreach( $clickabledirs as $nr => $dir )
		{
			if( $nr > 0 ) $cd .= $dir.'/';
			$r .= '/<a href="'.$this->curl( '#', $cd ).'">'.$dir.'</a>';
		}

		return $r;
	}


	/**
	 * get prefs from user's Settings
	 */
	function loadSettings()
	{
		global $UserSettings;

		$UserSettings->get_cond( $this->dirsattop,   'fm_dirsattop',   $this->User->ID );
		$UserSettings->get_cond( $this->permlikelsl, 'fm_permlikelsl', $this->User->ID );
		$UserSettings->get_cond( $this->fulldirsize, 'fm_fulldirsize', $this->User->ID );
		$UserSettings->get_cond( $this->showhidden,  'fm_showhidden',  $this->User->ID );
	}


	/**
	 * check permissions
	 *
	 * @param string for what? (upload)
	 * @return true if permission granted, false if not
	 */
	function perm( $for )
	{
		global $Debuglog;

		switch( $for )
		{
			case 'upload':
				return $this->User->check_perm( 'upload', 'any', false );

			default:  // return false if not defined
				$Debuglog->add( 'Filemanager: permission check for ['.$for.'] not defined!' );
				return false;
		}
	}

}

?>