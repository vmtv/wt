<?php

namespace Drupal\wt_common\TwigExtension;

class WtCommonTwigExtension extends \Twig_Extension {

  public function getFilters() {
    return [
      new \Twig_SimpleFilter('wt_common_dump', array($this, 'wtCommonDump')),
    ];
  }

  public function getName() {
    return 'wt_common.twig_extension';
  }

  public static function wtCommonDump($var) {
    return wt_common_dump($var);
  }
}
