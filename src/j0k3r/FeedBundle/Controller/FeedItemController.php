<?php

namespace j0k3r\FeedBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use j0k3r\FeedBundle\Document\Feed;

/**
 * FeedItem controller.
 */
class FeedItemController extends Controller
{
    /**
     * Lists all Feed documents.
     *
     * @Template()
     *
     * @return array
     */
    public function indexAction($slug)
    {
        $dm   = $this->getDocumentManager();
        $feed = $dm->getRepository('j0k3rFeedBundle:Feed')->findOneBySlug($slug);

        if (!$feed) {
            throw $this->createNotFoundException('Unable to find Feed document.');
        }

        $feeditems = $dm->getRepository('j0k3rFeedBundle:FeedItem')->findByFeedId($feed->getId());

        $deleteAllForm = $this->createDeleteAllForm($feed->getSlug());

        return array(
            'menu'            => 'feed',
            'feed'            => $feed,
            'feeditems'       => $feeditems,
            'delete_all_form' => $deleteAllForm->createView(),
        );
    }

    public function deleteAllAction(Request $request, $slug)
    {
        $dm   = $this->getDocumentManager();
        $feed = $dm->getRepository('j0k3rFeedBundle:Feed')->findOneBySlug($slug);

        if (!$feed) {
            throw $this->createNotFoundException('Unable to find Feed document.');
        }

        $form = $this->createDeleteAllForm($slug);
        $form->bind($request);

        if ($form->isValid()) {
            $res = $dm->getRepository('j0k3rFeedBundle:FeedItem')->deleteAllByFeedId($feed->getId());

            $this->get('session')->getFlashBag()->add('notice', $res['n'].' documents deleted!');
        }

        return $this->redirect($this->generateUrl('feed_edit', array('slug' => $slug)));
    }

    public function previewAction($id)
    {
        $dm       = $this->getDocumentManager();
        $feeditem = $dm->getRepository('j0k3rFeedBundle:FeedItem')->find($id);

        if (!$feeditem) {
            throw $this->createNotFoundException('Unable to find FeedItem document.');
        }

        return $this->container->get('templating')->renderResponse('j0k3rFeedBundle:FeedItem:content.html.twig', array(
            'title'   => $feeditem->getTitle(),
            'content' => $feeditem->getContent(),
            'url'     => $feeditem->getLink(),
            'modal'   => true,
        ));
    }

    public function testItemAction($slug)
    {
        $dm   = $this->getDocumentManager();
        $feed = $dm->getRepository('j0k3rFeedBundle:Feed')->findOneBySlug($slug);

        if (!$feed) {
            throw $this->createNotFoundException('Unable to find Feed document.');
        }

        $rssFeed = $this
            ->get('simple_pie_proxy')
            ->setUrl($feed->getLink())
            ->init();

        $parser = $this
            ->get('readability_proxy')
            ->setChoosenParser($feed->getParser());

        $firstItem = $rssFeed->get_item(0);
        $content   = $parser->parseContent($firstItem->get_link());

        return $this->container->get('templating')->renderResponse('j0k3rFeedBundle:FeedItem:content.html.twig', array(
            'title'   => $firstItem->get_title(),
            'content' => $content->content,
            'modal'   => true,
            'url'     => $content->url,
        ));
    }

    private function createDeleteAllForm($slug)
    {
        return $this->createFormBuilder(array('slug' => $slug))
            ->add('slug', 'hidden')
            ->getForm()
        ;
    }

    /**
     * Returns the DocumentManager
     *
     * @return DocumentManager
     */
    private function getDocumentManager()
    {
        return $this->get('doctrine.odm.mongodb.document_manager');
    }
}
