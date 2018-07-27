<?php

namespace App\Actions;

use App\Actions;
use App\Folders;
use App\Model\Task as TaskModel;
use App\Actions\Base as BaseAction;
use App\Actions\Copy as CopyAction;
use App\Model\Message as MessageModel;

class Flag extends BaseAction
{
    /**
     * Marks messages as flagged. This needs to add it to the
     * starred folder if set.
     *
     * @see Base for params
     */
    public function update(MessageModel $message, Folders $folders, array $options = [])
    {
        $this->setFlag($message, MessageModel::FLAG_FLAGGED, true, [], $options);

        // Overwrite and set the folder ID option
        $options = array_merge($options, [
            Actions::TO_FOLDER_ID => $folders->getStarredId()
        ]);

        if ($folders->getStarredId()) {
            (new CopyAction)->update($message, $folders, $options);
        }
    }

    public function getType()
    {
        return TaskModel::TYPE_FLAG;
    }
}
