<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Competition;
use App\CompetitionTeam;
use App\Team;
use App\Player;
use DateTime;

class LeagueController extends Controller
{
    public function importLeague($leagueCode) {

        $client = new Client([
            'base_uri' => 'http://api.football-data.org/v2/',
            'headers' => ['X-Auth-Token' => 'd20b5c4c12cd4beda7a764fa635a842a'],
            'http_errors' => false,
            'timeout'  => 2.0,
        ]);
        

        $competition_response = $client->request('GET', "competitions/{$leagueCode}/teams");
        $statusCode = $competition_response->getStatusCode();
        
        if($statusCode == 200) {
            $json = json_decode($competition_response->getBody()->getContents());
            $competition = new Competition();
            $competition->code = $json->competition->code;
            $competition->name = $json->competition->name;
            $competition->area_name = $json->competition->area->name;

            $competitionExist = Competition::where('code', $competition->code)->first();
            if(!empty($competitionExist) && is_object($competitionExist)) {
                $data = array(
                    'HttpCode' => 409,
                    'message' => 'League already imported'
                );
            }else{
                $competition->save();

                foreach ($json->teams as $tm) {
                    $team = new Team();
                    $team->name = $tm->name;
                    $team->tla = $tm->tla;
                    $team->shortname = $tm->shortName;
                    $team->area_name = $tm->area->name;
                    $team->email = $tm->email;
                    
                    $teamExist = Team::where('tla', $team->tla)
                                    ->where('shortname', $team->shortname)
                                    ->first();

                    //If the team already exist only update the relationship with the new league
                    if (!empty($teamExist) && is_object($teamExist)) {
                        $competitionTeam = new CompetitionTeam();
                        $competitionTeam->competition_id = $competition->id;
                        $competitionTeam->team_id = $teamExist->id;

                        $competitionTeam->save();
                    }else{
                        $team->save();
                        $competitionTeam = new CompetitionTeam();
                        $competitionTeam->competition_id = $competition->id;
                        $competitionTeam->team_id = $team->id;

                        $competitionTeam->save();

                        //Get players from team
                        $players_response = $client->request('GET', "teams/{$team->id}");
                        $statusCode = $players_response->getStatusCode();

                        if($statusCode == 200) {

                            $players_json = json_decode($players_response->getBody()->getContents());

                            foreach ($players_json->squad as $p) {
                                if($p->role == 'PLAYER'){
                                    $player = new Player();
                                    $player->team_id = $team->id;
                                    $player->name = $p->name;
                                    $player->position = $p->position;
                                    $player->dateofBirth = DateTime::createFromFormat('Y-m-d', substr($p->dateOfBirth,0, 10));
                                    $player->countryofbirth = $p->countryOfBirth;
                                    $player->nationality = $p->nationality;

                                    $player->save();
                                }
                            }

                            $data = array(
                                'HttpCode'  => 201,
                                'message' => 'Successfully imported'
                            );
                        }else{
                            if($statusCode == 404) {
                                $data = array(
                                    'HttpCode' => $statusCode,
                                    'message' => 'Not Found'
                                );
                            }elseif($statusCode == 504){
                                $data = array(
                                    'HttpCode' => $statusCode,
                                    'message' => 'Server Error'
                                );
                            }
                         }

                    }

                }
            }

        }else{
            if($statusCode == 404) {
                $data = array(
                    'HttpCode' => $statusCode,
                    'message' => 'Not Found'
                );
            }elseif($statusCode == 504){
                $data = array(
                    'HttpCode' => $statusCode,
                    'message' => 'Server Error'
                );
            }
        }
        
        return response()->json($data, $data['HttpCode']);

    }

    public function getPlayers($leagueCode) {
        $competition = Competition::where('code', $leagueCode)->first();

        if (!empty($competition) && is_object($competition)) {
            $counter = 0;
            foreach ($competition->teams as $team) {
                $players = Player::where('team_id', $team->id)->count();
                $counter += $players;
            }

            $data = array(
                'HttpCode' => 200,
                'total' => $counter
            );

        }else{
            $data = array(
                'HttpCode' => 404,
                'message' => 'Not Found'
            );
        }

        return response()->json($data, $data['HttpCode']);
    }

}
