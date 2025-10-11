<?php

namespace happycog\craftmcp\tools;

use Craft;
use PhpMcp\Server\Attributes\McpTool;
use happycog\craftmcp\actions\FieldFormatter;

class GetFields
{
    public function __construct(
        protected FieldFormatter $fieldFormatter,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[McpTool(
        name: 'get_fields',
        description: <<<'END'
        Get a list of all fields in Craft CMS. This is useful for understanding the available fields, their
        configurations, and the field handle that must be used when creating or updating entries.

        You can pass an optional fieldLayoutId, if you know it, to only get the fields associated with that layout.
        END
    )]
    public function get(?int $fieldLayoutId): array
    {
        return $fieldLayoutId
            ? $this->getFieldsForLayout($fieldLayoutId)
            : $this->getAllGlobalFields();
    }

    protected function getFieldsForLayout(int $fieldLayoutId): array
    {
        $layout = Craft::$app->getFields()->getLayoutById($fieldLayoutId);
        throw_unless($layout, "Field layout with ID {$fieldLayoutId} not found");

        // Preserve field ordering and include layout context
        return $this->fieldFormatter->formatFieldsForLayout($layout);
    }

    protected function getAllGlobalFields(): array
    {
        $fields = Craft::$app->getFields()->getAllFields('global');
        $result = [];
        foreach ($fields as $field) {
            $result[] = $this->fieldFormatter->formatField($field);
        }

        return $result;
    }

}
