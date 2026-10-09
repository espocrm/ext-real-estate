<div class="panel panel-default">
  <div class="panel-heading"><h4 class="panel-title">{{translate 'auctionHistory' category='labels' scope='RealEstateProperty'}}</h4></div>
  <div class="panel-body auction-history-body">
    {{#ifEqual state 'loading'}}<p>{{translate 'Loading' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'empty'}}<p>{{translate 'noAuctionHistory' category='labels' scope='RealEstateProperty'}}</p>{{/ifEqual}}
    {{#ifEqual state 'denied'}}<p>{{translate 'AccessDenied' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'error'}}<p>{{translate 'Error' category='labels' scope='Global'}}</p>{{/ifEqual}}
  </div>
</div>
