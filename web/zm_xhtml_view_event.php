<?php
//
// ZoneMinder web event view file, $Date$, $Revision$
// Copyright (C) 2003, 2004, 2005, 2006  Philip Coombes
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
//

if ( !canView( 'Events' ) )
{
	$view = "error";
	return;
}

if ( !isset($mode) )
{   
	$mode = "still";
}

if ( $user['MonitorIds'] )
{
	$mid_sql = " and MonitorId in (".join( ",", preg_split( '/["\'\s]*,["\'\s]*/', $user['MonitorIds'] ) ).")";
}
else
{
	$mid_sql = '';
}

$sql = "select E.*,M.Name as MonitorName,M.Width,M.Height from Events as E inner join Monitors as M on E.MonitorId = M.Id where E.Id = '$eid'$mid_sql";
$event = dbFetchOne( $sql );

if ( $fid )
{
	$frame = dbFetchOne( "select * from Frames where EventID = '$eid' and FrameId = '$fid'" );
}
elseif ( isset( $fid ) )
{
	$frame = dbFetchOne( "select * from Frames where EventID = '$eid' and Score = '".$event['MaxScore']."'" );
	$fid = $frame['FrameId'];
}

parseSort( true, '&amp;' );
parseFilter( $filter, true, '&amp;' );

$sql = "select E.* from Events as E inner join Monitors as M on E.MonitorId = M.Id where $sort_column ".($sort_order=='asc'?'<=':'>=')." '".$event[$sort_field]."'".$filter['sql'].$mid_sql." order by $sort_column ".($sort_order=='asc'?'desc':'asc');
$result = dbQuery( $sql );
while ( $row = dbFetchNext( $result ) )
{
	if ( $row[Id] == $eid )
	{
		$prev_event = dbFetchNext( $result );
		break;
	}
}

$sql = "select E.* from Events as E inner join Monitors as M on E.MonitorId = M.Id where $sort_column ".($sort_order=='asc'?'>=':'<=')." '".$event[$sort_field]."'".$filter['sql'].$mid_sql." order by $sort_column $sort_order";
$result = dbQuery( $sql );
while ( $row = dbFetchNext( $result ) )
{
	if ( $row[Id] == $eid )
	{
		$next_event = dbFetchNext( $result );
		break;
	}
}

$frames_per_page = 15;
$frames_per_line = 3;

$paged = $event['Frames'] > $frames_per_page;

noCacheHeaders();
header("Content-type: application/xhtml+xml" );
echo( '<?xml version="1.0" encoding="iso-8859-1"?>'."\n" );
?>
<!DOCTYPE html PUBLIC "-//WAPFORUM//DTD XHTML Mobile 1.0//EN" "http://www.wapforum.org/DTD/xhtml-mobile10.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<title><?= ZM_WEB_TITLE_PREFIX ?> - <?= $zmSlangEvent ?> - <?= $event['Name'] ?></title>
<link rel="stylesheet" href="zm_xhtml_styles.css" type="text/css"/>
</head>
<body> 
<table style="width: 100%">
<tr>
<td align="left"><?= makeLink( "$PHP_SELF?view=eventdetails&amp;eid=$eid", $event['Name'].($event['Archived']?'*':''), canEdit( 'Events' ) ) ?></td>
<td align="right"><?php if ( canEdit( 'Events' ) ) { ?><a href="<?= $PHP_SELF ?>?view=events&amp;action=delete&amp;mark_eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;limit=<?= $limit ?>&amp;page=<?= $page ?>"><?= $zmSlangDelete ?></a><?php } else { ?>&nbsp;<?php } ?></td>
</tr>
</table>
<?php
if ( $paged && !empty($page) )
{
?>
<table style="width:100%">
<tr>
<?php
	$pages = (int)ceil($event['Frames']/$frames_per_page);
	$max_shortcuts = 3;
?>
<?php
	if ( $fid )
		$page = ($fid/$frames_per_page)+1;
	if ( $page < 0 )
		$page = 1;
	if ( $page > $pages )
		$page = $pages;

	if ( $page > 1 )
	{
		if ( false && $page > 2 )
		{
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=1">&lt;&lt;</a></td>
<?php
		}
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=<?= $page-1 ?>">&lt;</a></td>
<?php
		$new_pages = array();
		$pages_used = array();
		$lo_exp = max(2,log($page-1)/log($max_shortcuts));
		for ( $i = 0; $i < $max_shortcuts; $i++ )
		{
			$new_page = round($page-pow($lo_exp,$i));
			if ( isset($pages_used[$new_page]) )
				continue;
			if ( $new_page <= 1 )
				break;
			$pages_used[$new_page] = true;
			array_unshift( $new_pages, $new_page );
		}
		if ( !isset($pages_used[1]) )
			array_unshift( $new_pages, 1 );

		foreach ( $new_pages as $new_page )
		{
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=<?= $new_page ?>"><?= $new_page ?></a></td>
<?php
		}
	}
?>
<td align="center"><?= $page ?></td>
<?php
	if ( $page < $pages )
	{
		$new_pages = array();
		$pages_used = array();
		$hi_exp = max(2,log($pages-$page)/log($max_shortcuts));
		for ( $i = 0; $i < $max_shortcuts; $i++ )
		{
			$new_page = round($page+pow($hi_exp,$i));
			if ( isset($pages_used[$new_page]) )
				continue;
			if ( $new_page > $pages )
				break;
			$pages_used[$new_page] = true;
			array_push( $new_pages, $new_page );
		}
		if ( !isset($pages_used[$pages]) )
			array_push( $new_pages, $pages );

		foreach ( $new_pages as $new_page )
		{
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=<?= $new_page ?>"><?= $new_page ?></a></td>
<?php
		}
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=<?= $page+1 ?>">&gt;</a></td>
<?php
		if ( false && $page < ($pages-1) )
		{
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=event&amp;mode=still&amp;eid=<?= $eid ?><?= $filter['query'] ?><?= $sort_query ?>&amp;page=<?= $pages ?>">&gt;&gt;</a></td>
<?php
		}
	}
?>
</tr>
</table>
<?php
}
?>
<?php
if ( $paged && !empty($page) )
{
	$lo_frame_id = (($page-1)*$frames_per_page)+1;
	$hi_frame_id = min( $page*$frames_per_page, $event['Frames'] );
}
else
{
	$lo_frame_id = 1;
	$hi_frame_id = $event['Frames'];
}
$sql = "select * from Frames where EventID = '$eid'";
if ( $paged && !empty($page) )
	$sql .= " and FrameId between $lo_frame_id and $hi_frame_id";
$sql .= " order by FrameId";
$alarm_frames = array();
foreach ( dbFetchAll( $sql ) as $row )
{
	if ( $row['Type'] == 'Alarm' )
	{
		$alarm_frames[$row['FrameId']] = $row;
	}
}
?>
<table style="width: 100%">
<?php
$scale = getDeviceScale( $event['Width'], $event['Height'], $frames_per_line );

$count = 0;
if ( version_compare( phpversion(), "4.3.10", ">=") )
	$fraction = sprintf( "%.2F", $scale/SCALE_BASE );
else
	$fraction = sprintf( "%.2f", $scale/SCALE_BASE );
$event_path = getEventPath( $event );
for ( $frame_id = $lo_frame_id; $frame_id <= $hi_frame_id; $frame_id++, $count++ )
{
	if ( $count%$frames_per_line == 0 )
	{
?>
<tr>
<?php
	}

	$image_path = sprintf( "%s/%0".ZM_EVENT_IMAGE_DIGITS."d-capture.jpg", $event_path, $frame_id );

	$capt_image = $image_path;
	if ( $scale == SCALE_BASE || !file_exists( ZM_PATH_NETPBM."/jpegtopnm" ) )
	{
		$anal_image = preg_replace( "/capture/", "analyse", $image_path );

		if ( file_exists($anal_image) && filesize( $anal_image ) )
		{
			$thumb_image = $anal_image;
		}
		else
		{
			$thumb_image = $capt_image;
		}
	}
	else
	{
		$thumb_image = preg_replace( "/capture/", "$scale", $capt_image );

		if ( !file_exists($thumb_image) || !filesize( $thumb_image ) )
		{
			$anal_image = preg_replace( "/capture/", "analyse", $capt_image );
			if ( file_exists( $anal_image ) )
				$command = ZM_PATH_NETPBM."/jpegtopnm -dct fast $anal_image | ".ZM_PATH_NETPBM."/pnmscalefixed $fraction | ".ZM_PATH_NETPBM."/ppmtojpeg --dct=fast > $thumb_image";
			else
				$command = ZM_PATH_NETPBM."/jpegtopnm -dct fast $capt_image | ".ZM_PATH_NETPBM."/pnmscalefixed $fraction | ".ZM_PATH_NETPBM."/ppmtojpeg --dct=fast > $thumb_image";
			exec( $command );
		}
	}
	$alarm_frame = $alarm_frames[$frame_id];
	$img_class = $alarm_frame?"alarm":"normal";
?>
<td align="center"><a href="<?= $PHP_SELF ?>?view=frame&amp;eid=<?= $eid ?>&amp;fid=<?= $frame_id ?>"><img src="<?= $thumb_image ?>" style="border: 0" width="<?= reScale( $event['Width'], $scale ) ?>" height="<?= reScale( $event['Height'], $scale ) ?>" class="<?= $img_class ?>" alt="<?= $frame_id ?>/<?= $alarm_frame?$alarm_frame['Score']:0 ?>"/></a></td>
<?php
	if ( $count%$frames_per_line == ($frames_per_line-1) )
	{
?>
</tr>
<?php
	}
}
if ( $count%$frames_per_line != 0 )
{
	while ( $count%$frames_per_line != ($frames_per_line-1) )
	{
?>
<td>&nbsp;</td>
<?php
	}
?>
</tr>
<?php
}
?>
</table>
</body>
</html>
