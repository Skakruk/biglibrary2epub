<?php

include_once("simple_html_dom.php");
ini_set('display_errors', 0);
$url = 'http://www.big-library.com.ua/book/91_Interpol_Mijnarodna_organizaciya_kriminalnoi_policii';
$booktitle = end(explode('/',$url));

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$page = curl_exec($ch);
curl_close($ch);

// Example. 
// Create a test book for download.
//error_reporting(E_ALL | E_STRICT);
// ePub uses XHTML 1.1, preferably strict.
$content_start =
"<?xml version=\"1.0\" encoding=\"utf-8\"?>\n"
. "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"\n"
. "    \"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd\">\n"
. "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n"
. "<head>"
. "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" />\n"
. "<link rel=\"stylesheet\" type=\"text/css\" href=\"styles.css\" />\n"
. "<title>Test Book</title>\n"
. "</head>\n"
. "<body>\n";

$bookEnd = "</body>\n</html>\n";

$html = str_get_html($page);

// setting timezone for time functions used for logging to work properly
date_default_timezone_set('Europe/Berlin');

$tStart = gettimeofday();
$tLast = $tStart;

$fileDir = './PHPePub';

include_once("EPub.php");

$book = new EPub();

// Title and Identifier are mandatory!
$book->setTitle($html->find('#bookName" h1', 0)->plaintext);
$book->setIdentifier($url, EPub::IDENTIFIER_URI); // Could also be the ISBN number, prefered for published books, or a UUID.
$book->setLanguage("en"); // Not needed, but included for the example, Language is mandatory, but EPub defaults to "en". Use RFC3066 Language codes, such as "en", "da", "fr" etc.
$book->setDescription($html->find('#bookAnotation',0)->plaintext );
$book->setAuthor($html->find('#bookAutor', 0)->plaintext, $html->find('#bookAutor', 0)->plaintext); 
$book->setPublisher('big-library.com.ua', 'http://www.big-library.com.ua/'); // I hope this is a non existant address :) 
$book->setDate(time()); // Strictly not needed as the book date defaults to time().
$book->setRights("Copyright and licence information specific for the book."); // As this is generated, this _could_ contain the name or licence information of the user who purchased the book, if needed. If this is used that way, the identifier must also be made unique for the book.
$book->setSourceURL($url);

$cssData = "body {\n  margin-left: .5em;\n  margin-right: .5em;\n  text-align: justify;\n}\n\np {\n  font-family: serif;\n  font-size: 10pt;\n  text-align: justify;\n  text-indent: 1em;\n  margin-top: 0px;\n  margin-bottom: 1ex;\n}\n\nh1, h2 {\n  font-family: sans-serif;\n  font-style: italic;\n  text-align: center;\n  background-color: #6b879c;\n  color: white;\n  width: 100%;\n}\n\nh1 {\n    margin-bottom: 2px;\n}\n\nh2 {\n    margin-top: -2px;\n    margin-bottom: 2px;\n}\n";

$book->addCSSFile("styles.css", "css1", $cssData);
$book->setCoverImage("Cover.jpg", file_get_contents('http://www.big-library.com.ua'.$html->find('#bookImg', 0)->src), "image/jpeg");

$i = 0;
foreach ($html->find('.link2') as $key => $page) {
	$i++;
	$page->href;
	if(!empty($page->href)){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $page->href);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$htmlpage = curl_exec($ch);
		curl_close($ch);
		$nhtml = str_get_html($htmlpage);
		$ptitle = $nhtml->find('#contentPageTop h2', 0)->plaintext;
		$pcontent = $nhtml->find('#contentText', 0)->innertext;
		$chapter = $content_start .
			"<h1>{$ptitle}</h1>\n" . 
			$pcontent
		 . $bookEnd;
		$book->addChapter($ptitle, "Chapter".sprintf("%03s", $i).".html", $chapter);
	}
}

$cover = $content_start . "<h1>Test Book</h1>\n<h2>By: John Doe Johnson</h2>\n" . $bookEnd;

$book->addChapter("Notices", "Cover.html", $cover);

$book->setSplitSize(15000); // For this test, we split at approx 15k. Default is 250000 had we left it alone.

// More advanced use of the splitter:
// Still using Chapter 4, but as you can see, "Chapter 4" also contains a header for Chapter 5.

$book->finalize(); // Finalize the book, and build the archive.

$book->saveBook('epub-filename', '.');

// Send the book to the client. ".epub" will be appended if missing.
$zipData = $book->sendBook($booktitle);

?>