/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2015 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/

Espo.define('real-estate:views/real-estate-request/list-matching', ['views/main', 'search-manager', 'real-estate:views/real-estate-request/record/panels/matching-properties'], function (Dep, SearchManager, Panel) {

    return Dep.extend({

        template: 'list',

        el: '#main',

        scope: 'RealEstateRequest',

        name: 'ListMatching',

        views: {
            header: {
                el: '#main > .page-header',
                view: 'views/header'
            }
        },

        searchPanel: true,

        searchManager: null,

        setup: function () {
            this.wait(true);

            var countLoaded = 0;

            var proceed = function () {
                if (countLoaded == 2) {
                    this.wait(false);
                }
            }.bind(this);

            this.getModelFactory().create('RealEstateRequest', function (model) {
                this.model = model;
                model.id = this.options.id;

                this.model.fetch().done(function () {
                    countLoaded++;
                    proceed();
                }.bind(this));
            }, this);

            this.getCollectionFactory().create('RealEstateProperty', function (collection) {
                this.collection = collection;

                this.collection.url = 'RealEstateRequest/' + this.options.id + '/matchingProperties';

                this.collection.maxSize = this.getConfig().get('recordsPerPage') || this.collection.maxSize;

                if (this.searchPanel) {
                    this.setupSearchManager();
                }

                this.setupSorting();

                if (this.searchPanel) {
                    this.setupSearchPanel();
                }

                countLoaded++;
                proceed();

            }, this);

        },

        setupSearchPanel: function () {
            this.createView('search', 'views/record/search', {
                collection: this.collection,
                el: '#main > .search-container',
                searchManager: this.searchManager,
            }, function (view) {
                this.listenTo(view, 'reset', function () {
                    this.collection.sortBy = this.defaultSortBy;
                    this.collection.asc = this.defaultAsc;

                    this.collection.orderBy = this.defaultOrderBy;
                    this.collection.order = this.defaultOrder;
                }, this);
            }.bind(this));
        },

        getSearchDefaultData: function () {
            return this.getMetadata().get('clientDefs.' + this.collection.name + '.defaultFilterData');
        },

        setupSearchManager: function () {
            var collection = this.collection;

            var searchManager = new SearchManager(collection, 'listMatching', false, this.getDateTime(), this.getSearchDefaultData());

            collection.where = searchManager.getWhere();
            this.searchManager = searchManager;
        },

        setupSorting: function () {
            if (!this.searchPanel) return;

            var collection = this.collection;

            this.defaultSortBy = collection.sortBy;
            this.defaultAsc = collection.asc;

            this.defaultOrderBy = collection.orderBy;
            this.defaultOrder = collection.order;
        },

        getRecordViewName: function () {
            return this.getMetadata().get(['clientDefs', this.collection.name, 'recordViews', 'listMatching'])
            ||
            'real-estate:views/real-estate-request/record/list-matching';
        },

        afterRender: function () {
            if (!this.hasView('list')) {
                this.loadList();
            }
        },

        loadList: function () {
            this.notify('Loading...');
            if (this.collection.isFetched) {
                this.createListRecordView(false);
            } else {
                this.listenToOnce(this.collection, 'sync', function () {
                    this.createListRecordView();
                }, this);
                this.collection.fetch();
            }
        },

        createListRecordView: function (fetch) {
            var listViewName = this.getRecordViewName();
            this.createView('list', listViewName, {
                collection: this.collection,
                el: this.options.el + ' .list-container',
                type: 'listForRequest',
                rowActionsView: 'real-estate:views/real-estate-property/record/row-actions/for-request'
            }, function (view) {
                view.render();
                view.notify(false);
                if (fetch) {
                    setTimeout(function () {
                        this.collection.fetch();
                    }.bind(this), 2000);
                }
            });
        },

        getHeader: function () {
            return this.buildHeaderHtml([
                '<a href="#'+this.scope+'">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>',
                '<a href="#'+this.scope+'/view/'+this.model.id+'">' + this.model.get('name') + '</a>',
                this.getLanguage().translate('Matching Properties', 'labels', this.scope)
            ]);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.model.get('name'));
        },

        actionSetInterested: function (data) {
            Panel.prototype.actionSetInterested.call(this, data);
        },

        actionSetNotInterested: function (data) {
            Panel.prototype.actionSetNotInterested.call(this, data);
        },

        actionUnsetNotInterested: function (data) {
            Panel.prototype.actionUnsetNotInterested.call(this, data);
        }

    });
});

