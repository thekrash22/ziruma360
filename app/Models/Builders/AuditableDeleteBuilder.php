<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builder that deletes the rows one by one through the model.
 *
 * A mass delete (`Model::query()->delete()`, `$relation->delete()`) goes
 * straight to the database and does not fire the model events, so the audit
 * trail of the deleted rows would be lost (spec §1.6). This builder removes
 * every row through the model, so each deletion leaves its trail.
 */
class AuditableDeleteBuilder extends Builder
{
    /**
     * Delete the rows of the query one by one through the model.
     *
     * @return int the number of deleted rows
     */
    public function delete()
    {
        if (isset($this->onDelete)) {
            return call_user_func($this->onDelete, $this);
        }

        $deleted = 0;

        foreach ($this->get() as $model) {
            if ($model->delete()) {
                $deleted++;
            }
        }

        return $deleted;
    }
}
