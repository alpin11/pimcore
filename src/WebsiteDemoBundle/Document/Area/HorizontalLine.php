<?php

namespace WebsiteDemoBundle\Document\Area;

use Pimcore\Document\Area\AbstractTemplateAreabrick;

class HorizontalLine extends AbstractTemplateAreabrick
{
    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return 'horizontal-line';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'Horiz. Line';
    }
}
