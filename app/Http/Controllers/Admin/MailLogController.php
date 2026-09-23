<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailMessage;
use App\Services\Mailer;
use App\Support\MailTopic;
use Illuminate\Http\Request;

/**
 * Mail-Protokoll: was ist rausgegangen, was nicht, und warum nicht.
 *
 * Ohne diese Sicht bleibt der Versand eine Blackbox - gerade bei Mails, die
 * gar nicht erst verschickt wurden, weil das Thema im Profil abgewaehlt ist.
 */
class MailLogController extends Controller
{
    public function index(Request $request)
    {
        $query = MailMessage::with(['user:id,firstname,lastname', 'triggeredBy:id,firstname,lastname'])
            ->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($topic = $request->query('topic')) {
            $query->where('topic', $topic);
        }
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn($q) => $q
                ->where('recipient_email', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%"));
        }

        return view('admin.mail-log.index', [
            'messages' => $query->paginate(50)->withQueryString(),
            'counts'   => MailMessage::selectRaw('status, COUNT(*) as anzahl')->groupBy('status')->pluck('anzahl', 'status'),
            'topics'   => MailTopic::TOPICS,
            'filters'  => ['status' => $request->query('status'), 'topic' => $request->query('topic'), 'q' => $search],
        ]);
    }

    /** Eine gescheiterte Mail erneut in die Warteschlange stellen. */
    public function retry(MailMessage $mailMessage, Mailer $mailer)
    {
        if (!$mailMessage->mailable) {
            return back()->with('error', 'Diese Mail lässt sich nicht erneut verschicken – die Vorlage ist nicht hinterlegt.');
        }

        $mailMessage->update(['status' => 'pending', 'attempts' => 0, 'error' => null]);
        $result = $mailer->processQueue(1);

        return back()->with('success', $result['sent'] > 0
            ? 'Mail wurde erneut verschickt.'
            : 'Erneuter Versand hat nicht geklappt – die Einzelheiten stehen in der Zeile.');
    }
}
