<div class="panel panel-default">
  <div class="panel-heading">
    <h4 class="panel-title">{{translate 'currentSaleCycleState' category='fields' scope='RealEstateProperty'}}</h4>
  </div>
  <div class="panel-body current-sale-body">
    {{#ifEqual state 'loading'}}<p>{{translate 'Loading' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'no-cycle'}}<p>{{translate 'chaoGia' category='labels' scope='RealEstateProperty'}}</p>{{/ifEqual}}
    {{#ifEqual state 'needs-price'}}<p><span class="label label-warning">{{translate 'choGia' category='labels' scope='RealEstateProperty'}}</span></p>{{/ifEqual}}
    {{#ifEqual state 'denied'}}<p>{{translate 'AccessDenied' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'ok'}}<p class="text-muted">…</p>{{/ifEqual}}
  </div>
</div>
