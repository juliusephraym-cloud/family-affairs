<?php

namespace App\Http\Controllers;

use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PollController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'question' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'],
            'options' => ['required', 'string'], 'opens_at' => ['required', 'date'], 'closes_at' => ['required', 'date', 'after:opens_at'],
            'eligibility' => ['required', Rule::in(['members', 'leaders'])], 'result_visibility' => ['required', Rule::in(['always', 'after_vote', 'after_close', 'leaders_only'])],
            'allow_multiple' => ['boolean'], 'is_anonymous' => ['boolean'],
        ]);
        $options = array_values(array_filter(array_map('trim', explode("\n", $d['options']))));
        if (count($options) < 2) {
            return back()->withInput()->withErrors(['options' => __('Weka angalau machaguo mawili (moja kila mstari).')]);
        }
        $poll = Poll::create(collect($d)->except('options')->all() + ['allow_multiple' => $request->boolean('allow_multiple'), 'is_anonymous' => $request->boolean('is_anonymous'), 'created_by' => $request->user()->id]);
        foreach ($options as $i => $label) {
            $poll->options()->create(['label' => mb_substr($label, 0, 255), 'sort' => $i]);
        }

        return redirect()->route('polls.show', $poll)->with('status', __('Kura imefunguliwa.'));
    }

    public function show(Request $request, Poll $poll): View
    {
        $user = $request->user();
        abort_unless($poll->isEligible($user), 404);
        $poll->load('options.votes.user.member', 'votes');

        return view('community.poll', ['poll' => $poll, 'showResults' => $poll->resultsVisibleTo($user), 'myVotes' => $poll->votes->where('user_id', $user->id)->pluck('poll_option_id')]);
    }

    public function vote(Request $request, Poll $poll): RedirectResponse
    {
        $user = $request->user();
        abort_unless($poll->isEligible($user) && $poll->isOpen(), 422);
        $d = $request->validate(['options' => ['required', 'array', $poll->allow_multiple ? 'min:1' : 'size:1'], 'options.*' => ['integer', Rule::exists('poll_options', 'id')->where('poll_id', $poll->id)]]);
        DB::transaction(function () use ($poll, $user, $d) {
            PollVote::where('poll_id', $poll->id)->where('user_id', $user->id)->delete();
            foreach (array_unique($d['options']) as $opt) {
                PollVote::create(['poll_id' => $poll->id, 'poll_option_id' => $opt, 'user_id' => $user->id]);
            }
        });

        return back()->with('status', __('Kura yako imehesabiwa.'));
    }
}
