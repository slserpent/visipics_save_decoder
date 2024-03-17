<?php
/*
Reverse engineering the Visipics save file format (.VSP extension). While it does contain image identifiers and metadata,
 it unfortunately does not contain any match groupings and is thus pretty useless. But here it is anyways for
 informational purposes. Written on PHP 7.4.
*/

$file = 'D:\Desktop\test.vsp';
$handle = fopen($file, "rb");
$contents = fread($handle, filesize($file));
fclose($handle);

//whole file is zlib compressed. "Either ZLIB_ENCODING_RAW, ZLIB_ENCODING_DEFLATE or ZLIB_ENCODING_GZIP"
$decompressed = zlib_decode($contents);
$dec_len = strlen($decompressed);

//header and version. should be "VisiPics 1.3"
$visipics_version = unpack("Z*", substr($decompressed, 0, 283))[1];

//directory list, presumably delimited for multiple
$directories = unpack("Z*", substr($decompressed, 283, 7501))[1];

//each file is 256 for filepath + 7528 for binary data
//every file scanned has an entry
$cur_position = 7784;
$file_count = 0;
$files = [];

while ($dec_len > $cur_position) {
	$filepath = unpack("Z*", substr($decompressed, $cur_position, 256))[1];

	/*
	4 bytes filesize
	2 bytes dos time
	2 bytes dos date
	4 bytes image height
	4 bytes image width
	2 bytes always null
	3 bytes RGB lightness?
	1 byte always null (or 4th channel?)
	3 bytes channel saturation? CMYK?
	2 bytes ??
	7500 bytes (2500 bytes per color) of scaled 50x50-pixel planar-RGB image data
	1 null byte
	*/
	$filedata = unpack("Vfilesize/vdostime/vdosdate/Vheight/Vwidth", substr($decompressed, $cur_position + 256, 16));

	//converts MS-DOS time to local time in a human-readable format
	// https://learn.microsoft.com/en-us/windows/win32/api/winbase/nf-winbase-dosdatetimetofiletime
	$timestamp = (new DateTime())->setDate(1980 + ($filedata['dosdate'] >> 9 & 0x7F), $filedata['dosdate'] >> 5 & 0x1F, $filedata['dosdate'] & 0x1F)->setTime($filedata['dostime'] >> 11 & 0x1F, $filedata['dostime'] >> 5 & 0x3F, ($filedata['dostime'] & 0x1F) * 2);

	$color_data = unpack("C*", substr($decompressed, $cur_position + 272, 11));

	$files[] = [
		"filepath" => $filepath,
		"filesize" => $filedata['filesize'],
		"file_createtime" => $timestamp->format(DateTimeInterface::RFC3339),
		"height" => $filedata['height'],
		"width" => $filedata['width'],
		"color" => $color_data
	];
	$cur_position += 7784;
	$file_count += 1;
}

print_r($files);

/*
$handle = fopen('D:\Desktop\test decomp.vsp', 'w');
fwrite($handle, $decompressed);
fclose($handle);
*/
