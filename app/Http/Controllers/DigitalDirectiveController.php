<?php

namespace App\Http\Controllers;

use App\Definitions\Mission;
use App\Definitions\Tour;
use App\Models\Statistic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Response;

class DigitalDirectiveController extends AppBaseController
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        /** @var Statistic $statistics */
        $statistics = Statistic::all();

        return view('dd20.index')
            ->with('dd20', $statistics);
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function find(Request $request)
    {
        $query = $request->query->get('query');
        $query = "$query";
        $player = User::query()
            ->select('id')
            ->orWhere('name', '=', $query)
            ->orWhere('steamid', '=', $query)
            ->firstOrFail();

        return Redirect::route('dd20.view', compact('player'));
    }

    /**
     * @param Request $request
     * @param string $userId
     *
     * @return Response
     */
    public function view(Request $request, User $player, Tour $tour)
    {
        $missions = collect([
            $tour->newInstance('tour_digital_directive_2')->missions(),
            $tour->newInstance('tour_digital_directive_1')->missions(),
        ]);

        $missions = $missions->flatten()->groupBy('map');
        $campaign = Statistic::query()
            ->where('steamid', '=', $player->steamid)
            ->where('target', '=', '[C:mvm_directive]')
            ->firstOrFail();

        $stats = Statistic::query()
            ->where('steamid', '=', $player->steamid)
            ->where('target', 'LIKE', '[MVMM:%')
            ->get()
            ->mapWithKeys(function(Statistic $statistic)
            {
                return [$statistic->name() => $statistic];
            });

        return view('dd20.view', compact('player', 'campaign', 'missions', 'stats'));
    }

    public function store(Request $request, User $player, Mission $mission)
    {
        $base32Def = $request->post('reference');
        $waves = collect($request->post('waves', []))->flip();

        if (!$base32Def)
        {
            return Redirect::back()->with('error', 'Cannot locate mission.');
        }

        $mission->fromJson(base64_decode($base32Def));

        $stat = Statistic::query()
            ->where('steamid', '=', $player->steamid)
            ->where('target', '=', "[MVMM:{$mission->title}]")
            ->first();

        // {"wave_1":true,"wave_1_once":true,"wave_1_duration":393,"updated":1618099520,"wave_2":true,"wave_2_once":true,"wave_2_duration":261,"wave_3":true,"wave_3_once":true,"wave_3_duration":153,"wave_4":true,"wave_4_once":true,"wave_4_duration":210,"wave_5":true,"wave_5_once":true,"wave_5_duration":248,"wave_6":true,"wave_6_once":true,"wave_6_duration":195,"wave_7":true,"wave_7_once":true,"wave_7_duration":106}
        $progress = [];
        if (!$request->post('erase', false))
        {
            $progress = $stat ? $stat->progress : [];
            for ($i = 0; $i < $mission->waves; $i++)
            {
                $n = $i + 1;
                if (isset($waves[$n]))
                {
                    $progress["wave_{$n}"] = true;
                    $progress["wave_{$n}_once"] = true;
                    $progress["wave_{$n}_duration"] = isset($progress["wave_{$n}_duration"]) ? $progress["wave_{$n}_duration"] : 999;
                }
                else
                {
                    $progress["wave_{$n}"] = false;
                }
            }
        }

        $progress['updated'] = now()->getTimestamp();

        if ($stat)
        {
            $stat->update(['progress' => $progress]);
        }
        else
        {
            $stat = new Statistic();
            $stat->fill([
                'steamid' => $player->steamid,
                'target' => "[MVMM:{$mission->title}]",
                'progress' => $progress,
            ]);
            $stat->save();
        }

        return Redirect::route('dd20.view', compact('player'))
            ->with('success', "Successfully updated {$player->name}!");
    }
}
