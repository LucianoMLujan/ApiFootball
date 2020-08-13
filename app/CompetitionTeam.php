<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CompetitionTeam extends Model
{
    protected $table = 'competition_team';

    public function competition()
    {
        return $this->belongsTo('App\Competition', 'competition_id');
    }

    public function team()
    {
        return $this->belongsTo('App\Team', 'team_id');
    }
}
