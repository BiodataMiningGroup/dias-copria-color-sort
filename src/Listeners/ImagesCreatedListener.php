<?php

namespace Dias\Modules\Copria\ColorSort\Listeners;

use Dias\Modules\Copria\ColorSort\Sequence;

class ImagesCreatedListener
{
    /**
     * Handle the event.
     *
     * Remove all color sort sequences for the transect since they don't include the
     * newly added images.
     *
     * @param int $id Transect ID
     * @param  array  $ids  Image ids
     * @return void
     */
    public function handle($id, array $ids)
    {
        Sequence::where('transect_id', $id)->delete();
    }
}