@props(['inline' => false])

@if($inline)
    <div {{ $attributes->merge(['style' => 'font-size: 11px; color: #475569;']) }}>
        thrumble.audit &middot; Developed by <a href="https://www.attrixtech.com" style="color: #1e3a5f;">Attrix Technologies</a>
        &middot; Marketed by <a href="https://www.infusionn.in" style="color: #1e3a5f;">Infusionn Pvt. Ltd</a>
    </div>
@else
    <footer {{ $attributes->merge(['class' => 'text-center text-muted small py-3']) }} style="font-size: 0.75rem;">
        &copy; {{ date('Y') }} <span class="fw-semibold">thrumble.audit</span>
        &middot; Developed by <a href="https://www.attrixtech.com" target="_blank" rel="noopener" class="text-decoration-none">Attrix Technologies</a>
        &middot; Marketed by <a href="https://www.infusionn.in" target="_blank" rel="noopener" class="text-decoration-none">Infusionn Pvt. Ltd</a>
    </footer>
@endif
