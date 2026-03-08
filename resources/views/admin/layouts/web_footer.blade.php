<footer style="background: {{ $data->footerbgcolor }} !important;">
  <div class="pt-10 px-5 px-md-10">
    <div class="container-fluid text-white">
      <div class="row pt-md doot">
        <div class="col-lg-4 mb-5 mb-lg-0 p-0">
          <a href="#">
            <img src="{{ asset($p.$data->footerlogo) }}" alt="Footer logo" style="height: 70px;">
          </a>
          <p class="pt-2">
            {!! $data->footertextsub  !!}   
            <!--Blue is a rideshare platform facilitating peer to peer ridesharing by means of connecting passengers who are in need of rides from drivers with available cars to get from point A to point B with the press of a button.-->
          </p>
        </div>
        <div class="col-lg-2 col-sm-4 ml-lg-auto mb-5 mb-lg-0">

          <h6 class="heading ml-n5 mb-3">Quick links</h6>
          <ul class="list-unstyled">
            <li><a href="{{ url('/') }}" class="text-white opacity-80">Home</a></li>
            <li><a href="{{ url('serviceareas') }}" class="text-white opacity-80">Service Locations</a></li>
            <li><a href="{{ url('compliance') }}" class="text-white opacity-80">Compliance </a></li>
            <li><a href="{{ url('contactus') }}" class="text-white opacity-80">Contact Us</a></li>
            <li><a href="{{ url('privacy') }}" class="text-white opacity-80">Privacy Policy</a></li>
            <li><a href="{{ url('terms') }}" class="text-white opacity-80">Terms & Conditions</a></li>
          </ul>
        </div>
        <div class="col-lg-2 col-sm-4 mb-5 mb-lg-0">
          <h6 class="heading ml-n5 mb-3">Rider</h6>
          <ul class="list-unstyled">
            <!-- <li><a href="#" class="text-white opacity-80">How it works</a></li>
            <li><a href="#" class="text-white opacity-80">Rider Requirements</a></li> -->
            <li><a href="{{ url('safety') }}" class="text-white opacity-80">Safety</a></li>
          </ul>
          <div class="row">
            <div class="col-md-12 mb-1">
              <a href="{{ url($data->userioslink) }}" target="_blank">
                <img src="{{ asset($p.$data->playstoreicon1) }}" alt="" class="w-50 w-md-75 wow slideInLeft animated" style="visibility: visible;">
              </a>
            </div>
            <div class="col-md-12 mb-1">
              <a href="{{ url($data->userandroidlink) }}" target="_blank">
                <img src="{{ asset($p.$data->playstoreicon2) }}" alt="" class="w-50 w-md-75 wow slideInRight animated" style="visibility: visible;">
              </a>
            </div>
          </div>
        </div>
        <div class="col-lg-2 col-md-4 mb-5 mb-lg-0">
          <h6 class="heading ml-n5 mb-3">Driver</h6>
          <ul class="list-unstyled text-small">
            <li><a href="{{ url('howdriving') }}" class="text-white opacity-80">How it works</a></li>
            <li><a href="{{ url('driverrequirements') }}" class="text-white opacity-80">Driver Requirements</a></li>
            <!-- <li><a href="{{ url('dmv') }}" class="text-white opacity-80">DMV check</a></li> -->
            <li><a href="{{ url('safety') }}" class="text-white opacity-80">Safety</a></li>
          </ul>
          <div class="row">
            <div class="col-md-12 mb-1">
              <a href="{{ url($data->driverioslink) }}" target="_blank">
                <img src="{{ asset($p.$data->playstoreicon1) }}" alt="" class="w-50 w-md-75 wow slideInLeft animated" style="visibility: visible;">
              </a>
            </div>
            <div class="col-md-12 mb-1">
              <a href="{{ url($data->driverandroidlink) }}" target="_blank">
                <img src="{{ asset($p.$data->playstoreicon2) }}" alt="" class="w-50 w-md-75 wow slideInLeft animated" style="visibility: visible;">
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="row align-items-center justify-content-md-between py-4 mt-4 delimiter-top border-top">
        <div class="col-md-6">
          <div class="copyright font-size-xs text-center text-md-left">
            {!! $data->footercopytextsub  !!}
            <!--© 2021 blue, LLC All rights reserved.
            <a href="#" target="blank" class="text-dark">
              Powered By Blue
            </a>-->
          </div>
        </div>
        <div class="col-md-6">
          <ul class="nav justify-content-center justify-content-md-end mt-3 mt-md-0">
            <li class="nav-item">
              <a class="nav-link" href="{{ url($data->footerinstagramlink) }}" target="_blank">
                <i class="fab fa-instagram text-white"></i>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="{{ url($data->footerfacebooklink) }} " target="_blank">
                <i class="fab fa-facebook text-white"></i>
              </a>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</footer>



{{-- ═══ WhatsApp Floating Chat Box ═══ --}}
@php
    $whatsappNumber = get_settings('whatsapp_number') ?? '';
    $whatsappMessage = get_settings('whatsapp_message') ?? 'Hello! I need help.';
@endphp
@if($whatsappNumber)
<style>
    .wa-float { position: fixed; bottom: 24px; right: 24px; z-index: 9999; }
    .wa-btn {
        display: flex; align-items: center; gap: 10px;
        background: #25d366; color: #fff; border: none; border-radius: 50px;
        padding: 14px 22px; font-size: 15px; font-weight: 600;
        box-shadow: 0 4px 16px rgba(37,211,102,.45);
        cursor: pointer; transition: .3s; text-decoration: none;
    }
    .wa-btn:hover { background: #1fb855; transform: translateY(-2px); box-shadow: 0 6px 24px rgba(37,211,102,.55); color: #fff; text-decoration: none; }
    .wa-btn svg { width: 28px; height: 28px; fill: #fff; flex-shrink: 0; }
    .wa-btn span { display: inline; }
    @media (max-width: 576px) { .wa-btn span { display: none; } .wa-btn { padding: 14px; border-radius: 50%; } }
</style>
<div class="wa-float">
    <a class="wa-btn" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $whatsappNumber) }}?text={{ urlencode($whatsappMessage) }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        <span>Chat with us</span>
    </a>
</div>
@endif

<!-- JAVASCRIPT -->
<!-- Libs JS -->
@include('admin.layouts.web_common_scripts')

</body>

</html>