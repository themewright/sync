<?php

namespace ThemeWright\Sync\Theme;

class BlockGroups
{
    /**
     * The request data.
     *
     * @var mixed
     */
    protected $data;

    /**
     * The theme functions instance.
     *
     * @var \ThemeWright\Sync\Theme\Functions
     */
    protected $functions;

    /**
     * The response messages.
     *
     * @var array
     */
    protected $messages;

    /**
     * Handles the block group files.
     *
     * @param  mixed  $data
     * @param  \ThemeWright\Sync\Theme\Functions  $functions
     * @param  array  $messages
     * @return void
     */
    public function __construct(&$data, &$functions, &$messages = [])
    {
        $this->data = &$data;
        $this->functions = &$functions;
        $this->messages = &$messages;
    }

    /**
     * Builds the block group code chunks.
     *
     * @return void
     */
    public function build()
    {
        foreach ($this->data->blockGroups as $blockGroup) {
            $blockList = implode(', ', array_map(function ($blockId) use ($blockGroup) {
                $i = array_search($blockId, array_column($blockGroup->blocks, 'id'));
                return "'{$blockGroup->blocks[$i]->name}'";
            }, $blockGroup->blockIds));

            $chunk = [
                'type' => 'block-group',
                'code' => [
                    "// Register block group: {$blockGroup->name} (#{$blockGroup->id})",
                    "TW_Block_Group::register(",
                    "\tarray(",
                    "\t\t'id'           => {$blockGroup->id},",
                    "\t\t'label'        => __( '{$blockGroup->label}', '{$this->data->domain}' ),",
                    "\t\t'name'         => '{$blockGroup->name}',",
                    "\t\t'blocks'       => array( {$blockList} ),",
                    "\t\t'button_label' => __( '{$blockGroup->buttonLabel}', '{$this->data->domain}' ),",
                    "\t\t'menu_order'   => {$blockGroup->menuOrder},",
                    "\t)",
                    ");",
                ],
            ];

            $chunk['code'] = implode(PHP_EOL, $chunk['code']);

            $this->functions->updateChunk($chunk);
        }
    }
}