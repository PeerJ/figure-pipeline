<?php

require __DIR__ . '/FlickrClient.php';

class FiguresHandler  {
  /**
   * @var FlickrClient
   */
  private $flickr;

  /**
   * Output directory for data
   *
   * @var string
   */
  private $output_dir;

  /**
   * Converts JATS figure nodes to XMP
   *
   * @var XSLTProcessor
   */
  private $processor;

  public function __construct($config, $output_dir) {
    $this->output_dir = $output_dir;
    $this->flickr = new FlickrClient($config);

    $stylesheet = new DOMDocument;
    $stylesheet->load(__DIR__ . '/../xsl/figure-to-xmp.xsl');

    $this->processor = new XSLTProcessor;
    $this->processor->importStylesheet($stylesheet);
  }

  /**
   * Iterate through each page of the article index feed.
   */
  public function fetch_feed($url) {
    while ($url) {
      print "Fetching feed: $url\n";

      $feed = json_decode(file_get_contents($url), true);

      array_walk($feed['_items'], array($this, 'fetch_article'));

      $url = $feed['_links']['next'] ? $feed['_links']['next']['href'] : null;
    };
  }

  /**
   * For each article, fetch the XML file.
   *
   * For each figure in each article, generate an XMP file and fetch the PNG file.
   */
  protected function fetch_article($item) {
    $url = $item['_links']['alternate']['xml']['href'];

    print "Fetching article: $url\n";

    $xml = file_get_contents($url);

    $doc = new DOMDocument;
    $doc->loadXML($xml);

    $xpath = new DOMXPath($doc);
    $xpath->registerNamespace('xlink', 'http://www.w3.org/1999/xlink');

    $article = $this->article_metadata($xpath);
    $this->processor->setParameter(null, $article);
    $dir = $this->output_dir . (int) $article['article-id'];

    if (!file_exists($dir)) {
      mkdir($dir, 0700, true);
    }

    foreach ($xpath->query('body//fig') as $figureNode) {
      $figureId = $figureNode->getAttribute('id');

      $xmp = $this->generate_xmp($figureNode);
      $figure = $this->figure_metadata($xmp, $article, $figureId);
      $path = $this->fetch_figure($figure, $dir);

      $xmpfile = preg_replace('/.png$/', '.xmp', $path);
      // TODO: find out why $xmp->save($xmpfile) contains more than the figure node
      file_put_contents($xmpfile, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $xmp->saveXML($xmp->documentElement));

      // TODO: check for existing Flickr ID in JSON file
      $figure['flickr_id'] = $this->upload_figure($path, $xmpfile);

      $jsonfile = preg_replace('/.png$/', '.json', $path);
      file_put_contents($jsonfile, json_encode($figure, JSON_PRETTY_PRINT));
    }
  }

  protected function article_metadata($xpath) {
    $subjects = array();

    foreach ($xpath->query('front/article-meta/article-categories/subj-group/subject') as $node) {
      $subjects[] = $node->textContent;
    }

    $authors = array();

    foreach ($xpath->query('front/article-meta/contrib-group/contrib[@contrib-type="author"]') as $node) {
      $parts = array(
        $xpath->evaluate('string(name/given-names)', $node),
        $xpath->evaluate('string(name/surname)', $node),
      );

      $authors[] = implode(' ', array_filter($parts));
    }

    return array(
      'journal-title' => $xpath->evaluate('string(front/journal-meta/journal-title-group/journal-title)'),
      'article-id' => $xpath->evaluate('string(front/article-meta/article-id[@pub-id-type="publisher-id"])'),
      'article-doi' => $xpath->evaluate('string(front/article-meta/article-id[@pub-id-type="doi"])'),
      'license-url' => $xpath->evaluate('string(front/article-meta/permissions/license/@xlink:href)'),
      'pub-date' => $xpath->evaluate('string(front/article-meta/pub-date[@date-type="pub"][@pub-type="epub"])'),
      'subjects' => implode(';', $subjects),
      'authors' => implode(';', $authors),
    );
  }

  protected function generate_xmp($node) {
    $doc = new DOMDocument;
    $doc->appendChild($doc->importNode($node, true));

    $xmp = $this->processor->transformToDoc($node);
    $xmp->formatOutput = true;

    return $xmp;
  }

  protected function figure_metadata($xmp, $article, $figureId) {
    $xpath = new DOMXPath($xmp);
    $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
    $identifier = $xpath->evaluate('string(.//dc:identifier)');

    $journal = preg_replace('/[^\w-]/', '-', strtolower($article['journal-title']));

    // TMP; TODO: content negotation for the image
    //$url = 'http://dx.doi.org/' + $doi;

    return array(
      'url' => sprintf('https://peerj.com/articles/%d/%s-full.png', $article['article-id'], $figureId),
      'doi' => preg_replace('/^doi:/', '', $identifier),
      'filename' => sprintf('%s-%d-%s.png', $journal, $article['article-id'], $figureId),
    );
  }

  protected function fetch_figure($figure, $dir) {
    $path = $dir . '/' . preg_replace('/[^\w-\.]/', '', $figure['filename']);

    if (!file_exists($path)) {
      $command = sprintf('wget --no-clobber --header="Accept:image/png" --output-document=%s %s',
        escapeshellarg($path), escapeshellarg($figure['url']));

      exec($command);
    }

    return $path;
  }

  protected function upload_figure($path, $xmpfile) {
    print "Writing XMP from $xmpfile\n";

    /*$command = sprintf('exiftool -overwrite_original -tagsfromfile %s -xmp %s',
      escapeshellarg($xmpfile), escapeshellarg($path));*/
    $command = sprintf('exiftool -overwrite_original -tagsfromfile %s -all:all %s',
      escapeshellarg($xmpfile), escapeshellarg($path));

    exec($command);

    printf("Uploading image (%s KB): %s\n", number_format(round(filesize($path) / 1000)), $path);

    $id = $this->flickr->upload($path, array(
      'is_public' => 1,
      'safety_level' => 1, // safe
      'content_type' => 3, // other
      'hidden' => 1, // not hidden
    ));

    if (!$id) {
      throw new \Exception('No Flickr ID');
    }

    return $id;
  }
}
