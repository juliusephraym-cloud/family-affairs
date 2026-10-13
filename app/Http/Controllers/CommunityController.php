<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Comment;
use App\Models\Poll;
use App\Models\Reaction;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityController extends Controller
{
    public const REACTIONS = ['❤️', '🙏', '👏', '😂', '🎉'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('show', 'all');
        $posts = Announcement::visibleTo($user)->with(['author.member', 'reactions'])->withCount('comments')
            ->when($filter === 'official', fn ($q) => $q->where('kind', 'official'))
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('body', 'like', "%{$s}%")))
            ->orderByDesc('is_pinned')->latest('published_at')->paginate(15)->withQueryString();

        return view('community.index', [
            'posts' => $posts,
            'polls' => Poll::with('options')->withCount('votes')->where('closes_at', '>', now()->subDays(30))->latest('opens_at')->limit(6)->get()->filter(fn ($p) => $p->isEligible($user)),
            'filter' => $filter,
            'reactions' => self::REACTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:10000'],
            'kind' => ['required', Rule::in(['post', 'official'])], 'is_important' => ['boolean'], 'is_pinned' => ['boolean'],
            'visibility' => ['required', Rule::in(['members', 'leaders'])],
        ]);
        if ($d['kind'] === 'official' && ! $user->hasPermission('community.announce_official')) {
            $d['kind'] = 'post';
        }
        $official = $d['kind'] === 'official';
        $post = Announcement::create([
            'title' => $d['title'], 'body' => $d['body'], 'kind' => $d['kind'],
            'is_important' => $official && $request->boolean('is_important'),
            'is_pinned' => $official && $request->boolean('is_pinned'),
            'visibility' => $user->isLeader() ? $d['visibility'] : 'members',
            'author_user_id' => $user->id, 'published_at' => now(), 'status' => 'published',
        ]);
        if ($official) {
            Audit::record('community.official', $post, 'Official announcement published');
        }

        return redirect()->route('community.show', $post)->with('status', __('Imechapishwa.'));
    }

    public function show(Request $request, Announcement $announcement): View
    {
        abort_unless(Announcement::visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);

        return view('community.show', ['post' => $announcement->load('author.member', 'comments.user.member', 'reactions'), 'reactions' => self::REACTIONS]);
    }

    public function comment(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless(Announcement::visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);
        $d = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $announcement->comments()->create(['user_id' => $request->user()->id, 'body' => $d['body']]);

        return back();
    }

    public function react(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless(Announcement::visibleTo($request->user())->whereKey($announcement->id)->exists(), 404);
        $d = $request->validate(['emoji' => ['required', Rule::in(self::REACTIONS)]]);
        $existing = Reaction::where(['reactable_type' => 'announcement', 'reactable_id' => $announcement->id, 'user_id' => $request->user()->id, 'emoji' => $d['emoji']])->first();
        $existing ? $existing->delete() : $announcement->reactions()->create(['user_id' => $request->user()->id, 'emoji' => $d['emoji']]);

        return back();
    }

    public function moderate(Request $request, Announcement $announcement): RedirectResponse
    {
        $d = $request->validate(['action' => ['required', Rule::in(['hide', 'show', 'pin', 'unpin', 'hide_comment'])], 'comment_id' => ['nullable', 'integer']]);
        match ($d['action']) {
            'hide' => $announcement->update(['status' => 'hidden']),
            'show' => $announcement->update(['status' => 'published']),
            'pin' => $announcement->update(['is_pinned' => true]),
            'unpin' => $announcement->update(['is_pinned' => false]),
            'hide_comment' => Comment::where('commentable_type', 'announcement')->where('commentable_id', $announcement->id)->whereKey($d['comment_id'])->update(['status' => 'hidden']),
        };
        Audit::record('community.moderated', $announcement, 'Moderation: '.$d['action']);

        return back();
    }
}
