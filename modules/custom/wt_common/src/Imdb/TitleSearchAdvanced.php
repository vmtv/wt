<?php

namespace Drupal\wt_common\Imdb;

class TitleSearchAdvanced extends \Imdb\TitleSearchAdvanced
{
  protected $title = null;
  protected $genres = [];
  protected $user_rating = [];
  protected $count = 50;
  protected $page = 1;

  /**
   * Set title search keys.
   * @param string $title.
   */
  public function setTitle($title)
  {
    $this->title = $title;
  }

  /**
   * Set which types of genres should be returned
   * @param array $genres e.g. [action, adventure]
   */
  public function setGenres(array $genres)
  {
    $this->genres = $genres;
  }

  /**
   * Set which types of genres should be returned
   * @param string $genre e.g. [action, adventure]
   */
  public function setGenre($genre)
  {
    $this->genres[] = $genre;
  }

  /**
   * Set the range to ratings should be returned
   * @param array $user_rating e.g. [min, max]
   */
  public function setUserRating(array $user_rating)
  {
    $this->user_rating = $user_rating;
  }

  /**
   * Set the limit of the resuts be returned
   * @param int $count
   */
  public function setCount(int $count)
  {
    $this->count = $count;
  }

  /**
   * Set the number of the page be returned
   * @param int $page
   */
  public function setPage(int $page)
  {
    $this->page = $page;
  }

  public function search()
  {
    $page = $this->pages->get($this->buildUrl());
    return $this->parse_results($page);
  }

  protected function buildUrl($context = null)
  {
    $queries = [];

    if ($this->titleTypes) {
      $queries['title_type'] = implode(',', $this->titleTypes);
    }

    if ($this->title) {
      $queries['title'] = $this->title;
    }

    if ($this->year) {
      $queries['year'] = $this->year;
    }

    if ($this->countries) {
      $queries['countries'] = implode(',', $this->countries);
    }

    if ($this->languages) {
      $queries['languages'] = implode(',', $this->languages);
    }

    if ($this->genres) {
      $queries['genres'] = implode(',', $this->genres);
    }

    if ($this->user_rating) {
      $queries['user_rating'] = implode(',', $this->user_rating);
    }

    if ($this->count) {
      $queries['count'] = $this->count;

      if ($this->page > 1) {
        $queries['start'] = $this->count * $this->page + 1;
      }
    }

    if ($this->sort) {
      $queries['sort'] = $this->sort;
    }

    return 'https://' . $this->imdbsite . '/search/title?' . http_build_query($queries);
  }

  /**
   * @param string html of page
   */
  protected function parse_results($page)
  {
    $doc = new \DOMDocument();
    @$doc->loadHTML('<?xml encoding="UTF-8">' . $page);
    $xp = new \DOMXPath($doc);
    $resultSections = $xp->query("//div[@class='article']//div[@class='lister-item mode-advanced']");

    $mtype = NULL;
    $ret = [];
    $findTitleType = TRUE;

    if (count($this->titleTypes) === 1) {
      $mtype = $this->getTitleType($this->titleTypes[0]);
      $findTitleType = FALSE;
    }

    foreach ($resultSections as $resultSection) {
      $titleElement = $xp->query(".//h3[@class='lister-item-header']/a", $resultSection)->item(0);
      $title = trim($titleElement->nodeValue);

      preg_match('/tt(\d{7,8})/', $titleElement->getAttribute('href'), $match);

      $id = $match[1];
      $thumbnail_url = NULL;
      $rating = NULL;
      $length = NULL;
      $ep_id = NULL;
      $ep_name = NULL;
      $ep_year = NULL;
      $is_serial = FALSE;

      if ($findTitleType) {
        $mtype = $this->parseTitleType($xp, $resultSection);
      }

      if (in_array($mtype, array('TV Series', 'TV Episode', 'TV Mini-Series'))) {
        $is_serial = TRUE;
      }

      foreach ($xp->query(".//*[contains(@class, 'lister-item-image')]", $resultSection) as $item) {
        $imgItems = $xp->query(".//img", $item);

        if ($imgItems->length > 0) {
          $thumbnail_url = $imgItems->item(0)->getAttribute('loadlate');

          if (!empty($thumbnail_url)) {
            break;
          }
        }
      }

      foreach ($xp->query(".//*[contains(@class, 'ratings-imdb-rating')]", $resultSection) as $item) {
        $value = (float) $item->getAttribute('data-value');

        if ($value >= 1 && $value <= 10) {
          $rating = $value;
          break;
        }
      }

      $lengthItems = $xp->query(".//span[contains(@class, 'runtime')]", $resultSection);
      $lengthString = $lengthItems->item(0)->nodeValue;

      preg_match('/(\d+)/', $lengthString, $match);

      if (isset($match[1])) {
        $length = (int) $match[1];
      }

      $genreItems = $xp->query(".//span[contains(@class, 'genre')]", $resultSection);
      $genre = trim($genreItems->item(0)->nodeValue);

      $yearItems = $xp->query(".//span[contains(@class, 'lister-item-year')]", $resultSection);
      $yearString = $yearItems->item(0)->nodeValue;

      preg_match('/\((\d+)/', $yearString, $match);

      if (isset($match[1])) {
        $year = (int) $match[1];
      } else {
        $year = NULL;
      }

      if ($mtype === 'TV Episode') {
        $episodeTitleElement = $xp->query(".//h3[@class='lister-item-header']/a", $resultSection)
          ->item(1);

        if ($episodeTitleElement) {
          $ep_name = $episodeTitleElement->nodeValue;
          preg_match('/tt(\d{7,8})/', $episodeTitleElement->getAttribute('href'), $match);
          $ep_id = $match[1];

          if ($yearItems->length > 1) {
            $yearString = $yearItems->item(1)->nodeValue;

            if ($yearString) {
              $ep_year = trim($yearString, '() ');
            }
          }
        }
      }

      $ret[$id] = [
        'imdbid' => $id,
        'title' => $title,
        'year' => $year,
        'type' => $mtype,
        'thumbnail_url' => $thumbnail_url,
        'rating' => $rating,
        'length' => $length,
        'genre' => $genre,
        'serial' => $is_serial,
        'episode_imdbid' => $ep_id,
        'episode_title' => $ep_name,
        'episode_year' => $ep_year
      ];
    }

    return $ret;
  }
}
