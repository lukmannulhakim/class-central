<?php
/**
 * Created by PhpStorm.
 * User: dhawal
 * Date: 7/14/14
 * Time: 12:01 PM
 */

namespace ClassCentral\ElasticSearchBundle\DocumentType;


use ClassCentral\CredentialBundle\Entity\Credential;
use ClassCentral\ElasticSearchBundle\MOOCReportArticleEntity;
use ClassCentral\ElasticSearchBundle\Types\DocumentType;
use ClassCentral\SiteBundle\Entity\Course;
use ClassCentral\SiteBundle\Entity\Initiative;
use ClassCentral\SiteBundle\Entity\Institution;
use ClassCentral\SiteBundle\Entity\Item;
use ClassCentral\SiteBundle\Entity\Language;
use ClassCentral\SiteBundle\Entity\Stream;
use ClassCentral\SiteBundle\Utility\CourseUtility;

/**
 * This document is used to display the results for autocomplete box
 * Class SuggestDocumentType
 * @package ClassCentral\ElasticSearchBundle\DocumentType
 */
class SuggestDocumentType extends DocumentType{

    /**
     * Retuns a string that represents the type of
     * @return mixed
     */
    public function getType()
    {
        return 'suggest';
    }

    /**
     * Returns the id for the document
     * @return mixed
     */
    public function getId()
    {
       return get_class($this->entity) . '_' . $this->entity->getId();
    }


    /**
     * Weights:
     * Subjects - 20
     * Provider, Institution - 18
     * Recent - 25,
     * Upcoming - 15 within a month, 10 otherwise
     * Self paced - 14
     *
     * @return array
     */
    public function getBody()
    {
        $rs = $this->container->get('review');
        $router = $this->container->get('router');
        $followService = $this->container->get('follow');
        $entity = $this->entity ;

        $body = array();
        $payload = array(); // contains data that would be useful for the frontend to format results
        if($this->entity instanceof Course)
        {

            $payload['type'] = 'course';
            $body['name_suggest']['input'] = array_merge(array($entity->getName()), $this->tokenize( $entity->getName() )) ;
            $body['name_suggest']['output'] = $entity->getName();

            // Boost the scores of upcoming, recent courses
            $weight = $this->getCourseWeight( $entity );
            if($weight > 0)
            {
                $body['name_suggest']['weight'] = $weight;
            }
            $payload['rating'] = $rs->getRatings($entity->getId());
            $rArray = $rs->getReviewsArray($entity->getId());
            $payload['reviewsCount'] = $rArray['count'];
            $payload['name'] = $entity->getName();
            $payload['nextSession'] = '';
            $ns = CourseUtility::getNextSession( $entity );
            if($ns)
            {
                $payload['nextSession'] = $ns->getDisplayDate();
            }
            // $provider
            $provider = 'Independent';
            if($entity->getInitiative())
            {
                $provider = $entity->getInitiative()->getName();
            }
            $payload['provider'] = $provider;

            // Get Institution
            $institutionName = '';
            if(!$entity->getInstitutions()->isEmpty())
            {
                $ins = $entity->getInstitutions()->first();
                $institutionName = $ins->getName();
            }
            $payload['institution'] = $institutionName;
            // Url
            $payload['url'] = $router->generate('ClassCentralSiteBundle_mooc', array('id' => $entity->getId(), 'slug' => $entity->getSlug()));
            $body['name_suggest']['payload'] = $payload;
        }

        // Subjects
        if($this->entity instanceof Stream)
        {

            $payload['type'] = 'subject';
            $body['name_suggest']['input'] =  array_merge(array($entity->getName(), $entity->getSlug()), $this->tokenize( $entity->getName() )) ;
            $body['name_suggest']['output'] = $entity->getName();
            $body['name_suggest']['weight'] = 700;

            $payload['name'] = $entity->getName();
            $payload['count'] = $entity->getCourseCount();
            $payload['numFollows'] = $followService->getNumFollowers(Item::ITEM_TYPE_SUBJECT, $entity->getId());
            // Url
            $payload['url'] = $router->generate('ClassCentralSiteBundle_stream', array('slug' => $entity->getSlug()));
            $body['name_suggest']['payload'] = $payload;
        }

        // Providers
        if($this->entity instanceof Initiative)
        {
            $payload['type'] = 'provider';

            $body['name_suggest']['input'] =  array_merge(array($entity->getName(), $entity->getCode()), $this->tokenize( $entity->getName() )) ;
            $body['name_suggest']['output'] = $entity->getName();
            $body['name_suggest']['weight'] = round(500 + $entity->getCount()/100); // boosting the score for providers with more courses

            $this->tokenize( $entity->getName() );
            $payload['name'] = $entity->getName();
            $payload['count'] = $entity->getCount();
            $payload['numFollows'] = $followService->getNumFollowers(Item::ITEM_TYPE_PROVIDER, $entity->getId());

            // Url
            $payload['url'] = $router->generate('ClassCentralSiteBundle_initiative', array('type' => strtolower($entity->getCode()) ));
            $body['name_suggest']['payload'] = $payload;
        }

        // Languages
        if($this->entity instanceof Language)
        {
            $payload['type'] = 'language';
            $body['name_suggest']['input'] =  array($entity->getName(), $entity->getCode(),$entity->getSlug() )   ;
            $body['name_suggest']['output'] = $entity->getName();
            $body['name_suggest']['weight'] = 500;

            $payload['name'] = $entity->getName();
            $payload['count'] = $entity->getCourseCount();
            // Url
            $payload['url'] = $router->generate('lang', array('slug' => strtolower($entity->getSlug()) ));
            $body['name_suggest']['payload'] = $payload;
        }

        // Institutions
        if($this->entity instanceof Institution)
        {
            $payload['type'] = 'institution';

            $body['name_suggest']['input'] =  array_merge(array($entity->getName(), $entity->getSlug()), $this->tokenize( $entity->getName() )) ;
            $body['name_suggest']['output'] = $entity->getName();
            $body['name_suggest']['weight'] = round(400 + $entity->getCount()/100); // boosting the score for institutions with more courses

            $payload['name'] = $entity->getName();
            $payload['count'] = $entity->getCount();
            $payload['numFollows'] = $followService->getNumFollowers(Item::ITEM_TYPE_INSTITUTION, $entity->getId());
            // Url
            $path = '';
            if($entity->getIsUniversity())
            {
                $path = 'ClassCentralSiteBundle_university';
            }
            else
            {
                $path = 'ClassCentralSiteBundle_institution';
            }
            $payload['url'] = $router->generate($path, array('slug' => strtolower( $entity->getSlug() ) ));
            $body['name_suggest']['payload'] = $payload;
        }

        // Credential
        if ($this->entity instanceof Credential)
        {
            $payload['type'] = 'credential';
            $certificateSlug = '';
            if ( $this->entity->getInitiative() )
            {
                $provider = $this->entity->getInitiative();
                // Certificate details
                $certificateSlug = $this->entity->getFormatter()->getCertificateSlug();
                if($provider->getName() == 'FutureLearn')
                {
                    $certificateSlug = '';
                }
            }

            $body['name_suggest']['input'] =  array_merge(array($entity->getName(), $entity->getSlug(),$certificateSlug), $this->tokenize( $entity->getName() )) ;
            $body['name_suggest']['output'] = $entity->getName();
            $body['name_suggest']['weight'] = 400;

            $payload['name'] = $entity->getName();
            $payload['count'] = 5;
            $payload['numFollows'] = $followService->getNumFollowers(Item::ITEM_TYPE_CREDENTIAL, $entity->getId());
            // Url
            $payload['url'] = $router->generate('credential_page', array('slug' => $entity->getSlug()));
            $body['name_suggest']['payload'] = $payload;
        }

        if($this->entity instanceof MOOCReportArticleEntity)
        {
            $payload['type'] = 'mooc_report_article';

            $weightBoost = 0;
            $now = new \DateTime();
            if($this->entity->getPinned())
            {
                $weightBoost += 200;
            }
            $ageOfPost = $now->diff($this->entity->getPublishedDate())->format("%a");
            if($ageOfPost == 0) $ageOfPost = 1;
            echo $ageOfPost;
            $weightBoost += (1/$ageOfPost) * 200;

            $body['name_suggest']['input'] = array_merge(array($entity->getTitle()), $this->tokenize( $entity->getTitle() )) ;
            $body['name_suggest']['output'] = $entity->getTitle();
            $body['name_suggest']['weight'] = 300 + (int)$weightBoost;

            $payload['name'] = $entity->getTitle();

            $payload['url'] = $entity->getLink();
            $body['name_suggest']['payload'] = $payload;
        }

        return $body;
    }

    /**
     * Retrieves the mapping for a particular type.
     * @return mixed
     */
    public function getMapping()
    {
        return array(
            "name_suggest" => array(
                "type" => "completion",
                "payloads" => true,
                "index_analyzer" => "standard",
                "search_analyzer" => "standard",
                "preserve_position_increments"=> false,
                "preserve_separators"=> false
            )
        );
    }

    private function tokenize( $text )
    {
        $result = preg_split('/((^\p{P}+)|(\p{P}*\s+\p{P}*)|(\p{P}+$))/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter( $result, function($str){
            return (strlen($str) > 3) && (bool) preg_match('//u', $str);
        });
    }

    /**
     * Boost the weight of courses that are upcoming or recent
     * @param Course $course
     */
    private function getCourseWeight(Course $course)
    {
        $weight = 0;
        $ns = CourseUtility::getNextSession( $course );
        if($ns) {
            $states = CourseUtility::getStates($ns);
            $date = $ns->getStartDate();
            if( in_array('recent',$states) )
            {
                if(in_array('ongoing',$states))
                {
                    return 175; // this means it has already started. Push it slightly down
                }
                return 350;
            }

            if ( in_array( 'upcoming', $states) )
            {
                $dt = new \DateTime();
                $dt->add(new \DateInterval('P30D'));

                if( $date < $dt)
                {
                    return 300;
                }

                // Upcoming but later than a month
                return 200;
            }

            if( in_array('selfpaced', $states) )
            {
                return 250;
            }

        }

        return $weight;
    }
}