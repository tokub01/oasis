{{--
    "Install Oasis" box (docs/oasis-pwa.md), used in Hinweise einstellen and on Funktionen. The `install` module in
    resources/js/oasis.js shows the matching part: the install button where the browser offers it (Chrome, Edge,
    Samsung Internet on Android and desktop), the steps for iPhone/iPad, or a note that Oasis already runs as an app.
    Without JavaScript the general steps stay visible.
--}}
<div wire:ignore data-oasis="install" class="flex flex-col gap-3">
    <p data-install-general class="m-0 text-ink-2">Oasis lässt sich wie eine App auf den Startbildschirm legen – ohne App-Store, mit eigenem Symbol und Vollbild. Öffne dazu das Browser-Menü und wähle „App installieren“ oder „Zum Startbildschirm hinzufügen“.</p>
    <button type="button" data-install-button hidden class="o-btn o-btn-md o-btn-dark self-start">Oasis installieren</button>
    <ol data-install-ios hidden class="m-0 flex flex-col gap-1 pl-5 text-ink-2">
        <li>Öffne Oasis in Safari.</li>
        <li>Tippe unten auf „Teilen“ (das Quadrat mit dem Pfeil nach oben).</li>
        <li>Wähle „Zum Home-Bildschirm“ und tippe auf „Hinzufügen“.</li>
        <li>Starte Oasis über das neue Symbol – dann gehen auch Push-Hinweise (ab iOS 16.4).</li>
    </ol>
    <p data-install-done hidden role="status" class="m-0 font-bold text-spring-text">Oasis läuft hier schon als App.</p>
</div>
