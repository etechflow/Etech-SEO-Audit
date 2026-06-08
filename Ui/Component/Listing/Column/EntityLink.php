<?php
declare(strict_types=1);

namespace Etechflow\SeoAudit\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Action column — links each issue to the admin edit page of its entity, so a
 * merchant can jump straight from a finding to the place that fixes it.
 */
class EntityLink extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $url = $this->buildUrl((string) ($item['entity_type'] ?? ''), (int) ($item['entity_id'] ?? 0));
            if ($url !== null) {
                $item[$name]['edit'] = ['href' => $url, 'label' => __('Edit')];
            }
            if (!empty($item['frontend_url'])) {
                $item[$name]['view_live'] = [
                    'href'   => (string) $item['frontend_url'],
                    'label'  => __('View on site'),
                    'target' => '_blank',
                ];
            }
        }
        return $dataSource;
    }

    private function buildUrl(string $type, int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }
        return match ($type) {
            'product'  => $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $id]),
            'category' => $this->urlBuilder->getUrl('catalog/category/edit', ['id' => $id]),
            'cms_page' => $this->urlBuilder->getUrl('cms/page/edit', ['page_id' => $id]),
            default    => null,
        };
    }
}
