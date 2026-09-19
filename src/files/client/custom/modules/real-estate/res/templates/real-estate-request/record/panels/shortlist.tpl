<div class="panel panel-default">
  <div class="panel-heading">
    <h4 class="panel-title">{{translate 'Shortlist' category='labels' scope='RealEstateRequest'}}</h4>
  </div>
  <div class="panel-body shortlist-panel-body">
    {{#ifEqual state 'loading'}}<p>{{translate 'Loading' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'empty'}}<p>{{translate 'noShortlist' category='labels' scope='RealEstateRequest'}}</p>{{/ifEqual}}
    {{#ifEqual state 'denied'}}<p>{{translate 'AccessDenied' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'error'}}<p>{{translate 'Error' category='labels' scope='Global'}}</p>{{/ifEqual}}
    {{#ifEqual state 'ok'}}
      <table class="table table-bordered">
        <thead><tr>
          <th>{{lang.propertyLabel}}</th>
          <th>{{lang.statusLabel}}</th>
          <th>{{lang.fitLabel}}</th>
          <th>{{lang.sentSnapshot}}</th>
        </tr></thead>
        <tbody>
          {{#each rows}}
          <tr>
            <td>{{propertyName}}</td>
            <td>{{statusLabel}}</td>
            <td>{{fitReason}}</td>
            <td>
              {{#if snapshot}}
                {{#if snapshot.hasPrice}}
                  {{snapshot.amount}} {{snapshot.currency}} ({{snapshot.unit}})<br>
                  <span class="text-muted">{{../lang.asOfLabel}}: {{snapshot.asOf}}<br>{{../lang.ruleLabel}}: {{snapshot.ruleVersion}}</span>
                {{else}}
                  <span class="label label-warning">{{../lang.choGia}}</span>
                {{/if}}
              {{else}}
                <span class="text-muted">—</span>
              {{/if}}
            </td>
          </tr>
          {{/each}}
        </tbody>
      </table>
    {{/ifEqual}}
  </div>
</div>