<?php

namespace ClassCentral\SiteBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;

class Tracking {
    private $container;

    const PAGEVIEW = 'pageview';
    const PAGEVIEW_INSTITUTION = 'pageview_institutition';
    const PAGEVIEW_SUBJECT = 'pageview_subject';
    const AD_CLICK = 'ad_click';
    const TIP_CLICK = 'tip_click';


    public function __construct(ContainerInterface $container) {
      $this->container = $container;
    }
    public function event($key) {
      return constant('self::' . $key);
    }
}
