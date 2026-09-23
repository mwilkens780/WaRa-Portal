{{--
    Gemeinsames Grundgeruest aller Portal-Mails.

    Bewusst schlichtes HTML mit Inline-Styles: Mailprogramme koennen kein
    modernes CSS, und externe Stylesheets werden ohnehin nicht geladen.
--}}
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'WaRa-Portal')</title>
</head>
<body style="margin:0; padding:0; background-color:#f3f4f6; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden; border:1px solid #e5e7eb;">

                    {{-- Kopf --}}
                    <tr>
                        <td style="background-color:#1B5EAB; padding:20px 28px;">
                            <p style="margin:0; font-size:18px; font-weight:600; color:#ffffff;">SG Wasserratten Norderstedt</p>
                            <p style="margin:4px 0 0; font-size:13px; color:#c7dbf5;">@yield('subtitle', 'WaRa-Portal')</p>
                        </td>
                    </tr>

                    {{-- Inhalt --}}
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Fuss --}}
                    <tr>
                        <td style="padding:18px 28px; background-color:#f9fafb; border-top:1px solid #e5e7eb;">
                            <p style="margin:0; font-size:12px; line-height:1.6; color:#6b7280;">
                                Diese Nachricht kommt vom WaRa-Portal der SG Wasserratten Norderstedt e.V.
                                @if(!empty($portalUrl))
                                    <br><a href="{{ $portalUrl }}" style="color:#1B5EAB;">{{ $portalUrl }}</a>
                                @endif
                            </p>
                            @hasSection('footer-note')
                                <p style="margin:10px 0 0; font-size:12px; line-height:1.6; color:#6b7280;">
                                    @yield('footer-note')
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>

                <p style="max-width:560px; margin:14px auto 0; font-size:11px; line-height:1.6; color:#9ca3af; text-align:center;">
                    Du bekommst diese Mail, weil du einen Zugang zum WaRa-Portal hast.
                    Welche Benachrichtigungen du erhältst, stellst du im Portal unter „Mein Profil“ ein.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
