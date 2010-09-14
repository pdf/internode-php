<?
  /* internode.php
   * 
   * PHP classes and routines for retrieving, caching, formatting and displaying
   * Internode PADSL usage.
   *
   * Written by peter Lieverdink <me@cafuego.net>
   * Copyright 2004 Intellectual Property Holdings Pty. Ltd.
   *
   * License: GPL; See http://www.gnu.org/copyleft/gpl.html#SEC1 for a full version.
   *
   * Usage: http://yourwebhost.com.au/internode.php for the RSS feed
   *    or: http://yourwebhost.com.au/internode.php?DISPLAY=1 for the PNG image.
   *
   * Required software: php4 with gd and curl support.
   *
   * 19/05/2004 - Initial revision.
   *              The software fetches and caches usage stats. Then displays either
   *              an RSS feed or a PNG image with the complete usage history.
   * 26/05/2004 - Updates.
   *              The script now checks for availability of gd and curl and also
   *              notifies the user if it can't write to the cache file.
   */

  // Your username and password, change these.
  define("INTERNODE_USERNAME", "replace_with_your_username");
  define("INTERNODE_PASSWORD", "replace_with_your_password");

  // Graph area size, tweak if you really must.
  define("IMAGE_WIDTH", 550);
  define("IMAGE_HEIGHT", 175);

  // Don't modify anything else!
  define("DISPLAY", INTERNODE_USAGE);

  define("INTERNODE_HOST", "accounts.internode.on.net");
  define("INTERNODE_URI", "/cgi-bin/padsl-usage");
  define("INTERNODE_LOGIN", "/cgi-bin/login");
  define("INTERNODE_CACHE", ini_get("upload_tmp_dir")."/internode.cache");

  define("INTERNODE_USAGE", 0);
  define("INTERNODE_HISTORY", 1);
 
  define("IMAGE_BORDER", 10);
  define("IMAGE_BORDER_LEFT", 60);
  define("IMAGE_BORDER_BOTTOM", 40);

  define("INTERNODE_VERSION", 3);

  class history {
    var $date = null;
    var $usage = 0;
    function history($str) {
      $arr = explode(" ", $str);
      $this->date = mktime(0, 0, 0, substr($arr[0], 2, 2), substr($arr[0], 4, 2), substr($arr[0], 0, 2));
      $this->usage = $this->floatval($arr[1]);
    }
    function floatval($strValue) {
      $floatValue = ereg_replace("(^[0-9]*)(\\.|,)([0-9]*)(.*)", "\\1.\\3", $strValue);
      if (!is_numeric($floatValue)) $floatValue = ereg_replace("(^[0-9]*)(.*)", "\\1", $strValue);
      if (!is_numeric($floatValue)) $floatValue = 0;
      return $floatValue;
    }
  }

  class internode {

    var $used = 0;
    var $quota = 0;
    var $remaining = 0;
    var $percentage = 0;
    var $history = null;

    function internode() {

      if(!file_exists(INTERNODE_CACHE))
        $this->refresh_cache();
      else if( filemtime(INTERNODE_CACHE) < (time() - 3600))
        $this->refresh_cache();

      $this->read_cache();

    }

    function refresh_cache() {
      $usage = $this->fetch_data(INTERNODE_USAGE);
      echo "<p>Usage: $usage\n";
      $history = $this->fetch_data(INTERNODE_HISTORY);
      echo "<p>History: $history\n";
      $fp = fopen(INTERNODE_CACHE, "w");
      if($fp) {
        fputs($fp, $usage);
        fputs($fp, $history);
        fclose($fp);
      }
    }

    function read_cache() {
      if($fp = fopen(INTERNODE_CACHE, "r") ) {
        $arr = explode(" ", trim(fgetss($fp, 4096)));
        $this->used = $arr[0];
        $this->quota = $arr[1];
        $this->remaining = $this->quota - $this->used;
        $this->percentage = 100 * $this->used / $this->quota;
        $this->history = array();
	while(!feof($fp)) {
	  if( ($str = trim(fgetss($fp, 4096))) != "") {
	    array_push($this->history, new history($str) );
	  }
	}
	fclose($fp);
      }
    }

    function fetch_data($param) {
      $url = "https://".INTERNODE_HOST.INTERNODE_URI;

      $o = curl_init();
      curl_setopt($o, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($o, CURLOPT_URL, $url);
      curl_setopt($o, CURLOPT_POST, 1);
      curl_setopt($o, CURLOPT_POSTFIELDS, $this->make_data($param) );
      
      $result = curl_exec($o); // run the whole process
      curl_close($o); 
      return $result;
    }

    function make_data($param) {
      // Internode don't like urlencoded data...?
      if($param == INTERNODE_HISTORY)
        return "username=".INTERNODE_USERNAME."@internode.on.net&password=".INTERNODE_PASSWORD."&history=1&iso=1&";
      return "username=".INTERNODE_USERNAME."@internode.on.net&password=".INTERNODE_PASSWORD."&iso=1&";
    }

    function display($param) {
      switch($param) {
        case INTERNODE_HISTORY:
	  $this->display_history();
	  break;
        default:
	  $this->display_usage();
	  break;
      }
    }

    function display_usage() {
      header("Content-type: text/xml");
      echo "<?xml version=\"1.0\"?>\n";
      echo "<!-- RSS generated by Internode Usage v.". INTERNODE_VERSION ." - PHP ".phpversion()." ".strftime("%d/%m/%Y %H:%M:%S %Z")." -->\n";
      echo "<rss version=\"2.0\" xmlns:blogChannel=\"http://backend.userland.com/blogChannelModule\">\n";
      echo "<channel>\n";
      echo "<title>Internode ADSL Usage</title>\n";
      echo "<link>https://".INTERNODE_HOST.INTERNODE_LOGIN."</link>\n";
      echo "<description>Internode ADSL Usage for ".INTERNODE_USERNAME."@internode.on.net</description>\n";
      echo "<language>en-au</language>\n";
      echo "<copyright>Copyright 2004 Intellectual Property Holdings Pty. Ltd.</copyright>\n";
      echo "<docs></docs>\n";
      echo "<generator>Internode Usage v.". INTERNODE_VERSION ." - PHP ".phpversion()."</generator>\n";
      echo "<category domain=\"Syndic8\">1765</category>\n";
      echo "<managingEditor>".INTERNODE_USERNAME."@internode.on.net</managingEditor>\n";
      echo "<webMaster>webmaster@internode.on.net</webMaster>\n";
      echo "<ttl>3600</ttl>\n";
      echo "<item>\n";
      printf("  <title>Used: %.2f Gb</title>\n", $this->used/1000 );
      echo "</item>\n";
      echo "<item>\n";
      printf("  <title>Quota: %d Gb</title>\n", $this->quota/1000 );
      echo "</item>\n";
      echo "<item>\n";
      printf("  <title>Remaining: %.2f Gb</title>\n", $this->remaining/1000 );
      echo "</item>\n";
      echo "<item>\n";
      printf("  <title>Percentage: %.2f %% </title>\n", $this->percentage );
      echo "</item>\n";
      echo "</channel>\n";
      echo "</rss>\n";
    }

    function display_history() {
      if(!function_exists("imagepng")) {
        die("Sorry, this PHP installation cannot create dynamic PNG images");
      }
    
      header("Content-type: image/png");

      // Create image of specified size (and leave space for the borders)
      //
      $im = imagecreate(IMAGE_WIDTH + (2*IMAGE_BORDER) + IMAGE_BORDER_LEFT, IMAGE_HEIGHT + (2*IMAGE_BORDER) + IMAGE_BORDER_BOTTOM);

      // Allocate some colours.
      //
      $white = imagecolorallocate($im, 255,255,255);
      $black = imagecolorallocate($im, 0,0,0);
      $red = imagecolorallocate($im, 224,0,0);
      $green = imagecolorallocate($im, 0,224,0);
      $blue = imagecolorallocate($im, 0,0,224);
      $orange = imagecolorallocate($im, 224,224,0);

      // Draw three dashed background lines.
      //
      $dy = (IMAGE_HEIGHT-(2*IMAGE_BORDER)-IMAGE_BORDER_BOTTOM)/4;
      for($i = 1; $i < 4; $i++) {
        imagedashedline($im, IMAGE_BORDER_LEFT+IMAGE_BORDER, IMAGE_BORDER+($i*$dy), IMAGE_WIDTH+IMAGE_BORDER_LEFT-IMAGE_BORDER, IMAGE_BORDER+($i*$dy), $black);
      }

      // Calculate bar width.
      //
      $dx = IMAGE_WIDTH / (count($this->history) + 1);

      // Find scale maximum.
      //
      for($i = 0; $i < count($this->history); $i++) {
        if($this->history[$i]->usage > $max)
          $max = $this->history[$i]->usage;
        $total += $this->history[$i]->usage;
      }

      // Find where we need to right-align the y axis.
      //
      $len_max = imagefontwidth(2) * (1+(strlen(sprintf("%.1f Mb", $max))));
      $len_mmt = imagefontwidth(2) * (1+(strlen(sprintf("%.1f Mb", ($max*3/4)))));
      $len_med = imagefontwidth(2) * (1+(strlen(sprintf("%.1f Mb", ($max/2)))));
      $len_mmb = imagefontwidth(2) * (1+(strlen(sprintf("%.1f Mb", ($max/4)))));
      $len_min = imagefontwidth(2) * (1+(strlen("0.0 Mb")));
      $len_date = imagefontwidth(2) * (1+(strlen( strftime("%d %b %y", $this->history[count($this->history)]->date))));

      // Draw scale figures on y axis.
      //
      imagestring($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER-$len_max, IMAGE_BORDER-(imagefontheight(2)/2), sprintf("%.1f Mb", $max), $black);
      imagestring($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER-$len_mmt, IMAGE_BORDER+$dy-(imagefontheight(2)/2), sprintf("%.1f Mb", ($max*3/4)), $black);
      imagestring($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER-$len_med, IMAGE_BORDER+(2*$dy)-(imagefontheight(2)/2), sprintf("%.1f Mb", ($max/2)), $black);
      imagestring($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER-$len_mmb, IMAGE_BORDER+(3*$dy)-(imagefontheight(2)/2), sprintf("%.1f Mb", ($max/4)), $black);
      imagestring($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER-$len_min, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER-(imagefontheight(2)/2), "0.0 Mb", $black);

      // Find out the interval for x axis labels.
      //
      $mod = intval(count($this->history)/8);

      // Draw usage bars and x axis.
      // When usage is NEGATIVE, draw bar UP anyway but in blue.
      //
      for($i = 0; $i < count($this->history); $i++) {
	if($this->history[$i]->usage > 0) {
	  $y = $this->history[$i]->usage * (IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-(2*IMAGE_BORDER)) / $max;
          imagefilledrectangle($im, IMAGE_BORDER_LEFT+IMAGE_BORDER+($i*$dx), (IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER-$y), IMAGE_BORDER_LEFT+IMAGE_BORDER+($i*$dx)+$dx, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $green);
	} else { 
	  $y = (abs($this->history[$i]->usage)) * (IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-(2*IMAGE_BORDER)) / $max;
          imagefilledrectangle($im, IMAGE_BORDER_LEFT+IMAGE_BORDER+($i*$dx), (IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER-$y), IMAGE_BORDER_LEFT+IMAGE_BORDER+($i*$dx)+$dx, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $orange);
	}
	if($i % $mod == 0)
          imagestringup($im, 2, IMAGE_BORDER_LEFT+IMAGE_BORDER+($i*$dx)-(imagefontheight(2)/2)+($dx/2), IMAGE_HEIGHT-IMAGE_BORDER-IMAGE_BORDER_BOTTOM+$len_date, strftime("%d %b %y", $this->history[$i]->date), $black);
      }

      // Draw 0-max border around the graph.
      //
      imageline($im, IMAGE_BORDER_LEFT+IMAGE_BORDER, IMAGE_BORDER, IMAGE_WIDTH+IMAGE_BORDER_LEFT-IMAGE_BORDER, IMAGE_BORDER, $black);
      imageline($im, IMAGE_BORDER_LEFT+IMAGE_BORDER, IMAGE_BORDER, IMAGE_BORDER_LEFT+IMAGE_BORDER, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $black); 
      imageline($im, IMAGE_WIDTH+IMAGE_BORDER_LEFT-IMAGE_BORDER, IMAGE_BORDER, IMAGE_WIDTH+IMAGE_BORDER_LEFT-IMAGE_BORDER, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $black); 
      imageline($im, IMAGE_BORDER_LEFT+IMAGE_BORDER, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, IMAGE_WIDTH+IMAGE_BORDER_LEFT-IMAGE_BORDER, IMAGE_HEIGHT-IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $black);

      // And now just add a footer.
      //
      $footer = sprintf("PADSL usage graph %s - %s for %s@internode.on.net", strftime("%d/%m/%Y", $this->history[0]->date), strftime("%d/%m/%Y", $this->history[count($this->history)-1]->date), INTERNODE_USERNAME );
      imagestring($im, 3, (IMAGE_BORDER_LEFT+IMAGE_WIDTH+(2*IMAGE_BORDER))/2 - imagefontwidth(3) * (strlen($footer)/2), IMAGE_HEIGHT+IMAGE_BORDER_BOTTOM-IMAGE_BORDER, $footer, $black);

      $copyright = sprintf("Generated by internode.php v.%d - Copyright 2004 Intellectual Property Holdings Pty. Ltd.", INTERNODE_VERSION );
      imagestring($im, 1, (IMAGE_BORDER_LEFT+IMAGE_WIDTH+(2*IMAGE_BORDER))/2 - imagefontwidth(1) * (strlen($copyright)/2), IMAGE_HEIGHT+IMAGE_BORDER_BOTTOM+IMAGE_BORDER, $copyright, $black);

      // Output image and deallocate memory.
      //
      imagepng($im);
      unset($im);
    }
  }

  // Check installation options.
  //
  if(!function_exists('curl_init'))
    die("Your PHP installation is missing the CURL extension.");
  if(!CURLOPT_SSLVERSION)
    die("Your CURL version does not have SSL support enabled.");
  if(!file_exists(INTERNODE_CACHE)) {
    if(!$fp = @fopen(INTERNODE_CACHE, "w")) {
      die("Cannot create cache file '".INTERNODE_CACHE."'.\n\nPlease set upload_tmp_dir to a directory with mode 1777 in your php.ini");
    } else {
      @unlink(INTERNODE_CACHE);
    }
  } else {
    if(!is_writable(INTERNODE_CACHE)) {
      die("Cannot write data to cache file '".INTERNODE_CACHE."'.\n\nPlease set upload_tmp_dir to a directory with mode 1777 in your php.ini");
    }
  }

  $in = new internode();
  $in->display( intval($_GET['DISPLAY']) );
?>