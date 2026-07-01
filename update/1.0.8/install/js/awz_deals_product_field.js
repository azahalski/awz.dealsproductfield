(function() {
    'use strict';

    // Глобальное пространство имен для модуля
    window.AwzDealsProductField = window.AwzDealsProductField || {};

    // Хранилище экземпляров полей по fieldName
    const fieldsInstances = {};

    // API URL для получения товаров сделки
    const API_URL = '/bitrix/services/main/ajax.php?action=awz:dealsproductfield.api.field.getDealProducts';

    // Класс для работы с полем
    function DealsProductField(fieldName, container) {
        this.fieldName = fieldName;
        this.container = container;
        this.valueInput = container.querySelector('.awz-deals-product-value');
        this.dealsList = container.querySelector('.awz-deals-selected-list');
        this.dealsData = JSON.parse(this.valueInput.dataset.dealsData || '[]');
        this.messages = this.getMessages(container);
        this.isMultiple = container.dataset.multiple === 'Y';
        this.originalValue = this.valueInput.value;

        this.init();
    }

    DealsProductField.prototype.getMessages = function(container) {
        const messages = container.dataset.messages;
        if (messages) {
            return JSON.parse(messages);
        }
        return {
            noDeals: 'Нет выбранных сделок',
            totalProducts: 'Всего товаров',
            noProducts: 'Нет товаров',
            selectedProducts: 'Выбрано товаров',
            loading: 'Загрузка',
            loadError: 'Ошибка загрузки',
            selectorNotAvailable: 'Селектор сделок недоступен',
            unnamedProduct: 'Без названия',
            selectProducts: 'Выберите товары'
        };
    };

    DealsProductField.prototype.init = function() {
        // Если ничего не выбрано и есть текущая сделка, добавляем её по умолчанию
        this.initDefaultDeal();
        // Мигрируем старую структуру данных в новую (добавляем selectedProductsData)
        this.migrateDataStructure();
        this.renderDeals();
        this.initTagSelector();
    };

    DealsProductField.prototype.migrateDataStructure = function() {
        // Мигрируем старую структуру (только selectedProducts с ID) в новую (selectedProductsData с полными данными)
        const dealIds = Object.keys(this.dealsData);
        let migrated = false;
        
        dealIds.forEach(function(dealId) {
            const deal = this.dealsData[dealId];
            // Если есть selectedProducts, но нет selectedProductsData
            if (deal && Array.isArray(deal.selectedProducts) && deal.selectedProducts.length > 0 && 
                (!deal.selectedProductsData || !Array.isArray(deal.selectedProductsData) || deal.selectedProductsData.length === 0)) {
                // Если есть products, формируем selectedProductsData из них
                if (Array.isArray(deal.products) && deal.products.length > 0) {
                    const selectedProductsData = [];
                    deal.selectedProducts.forEach(function(productId) {
                        const product = deal.products.find(function(p) {
                            return parseInt(p.id) === parseInt(productId);
                        });
                        if (product) {
                            selectedProductsData.push({
                                id: product.id,
                                name: product.name || '',
                                price: product.price || 0,
                                quantity: product.quantity || 0,
                                measureName: product.measureName || '',
                                productId: product.productId || 0
                            });
                        }
                    });
                    this.dealsData[dealId].selectedProductsData = selectedProductsData;
                    migrated = true;
                }
            }
        }.bind(this));
        
        if (migrated) {
            this.saveDealsData();
        }
    };

    DealsProductField.prototype.initDefaultDeal = function() {
        // Проверяем, есть ли выбранные сделки
        const dealIds = Object.keys(this.dealsData);
        
        // Если сделок нет и есть ID текущей сделки, добавляем её по умолчанию
        if (dealIds.length === 0) {
            const currentDealId = this.container.dataset.currentDeal;
            if (currentDealId && currentDealId !== '0') {
                const dealTitle = 'Сделка #' + currentDealId;
                this.dealsData[currentDealId] = {
                    id: currentDealId,
                    title: dealTitle,
                    products: [],
                    selectedProducts: []
                };
                this.saveDealsData();
                console.log('Добавлена текущая сделка по умолчанию:', currentDealId);
            }
        }
    };

    DealsProductField.prototype.initTagSelector = function() {
        if (typeof BX === 'undefined' || !BX.UI || !BX.UI.EntitySelector) {
            return;
        }

        const instance = this;

        // Находим контейнер для тагселектора
        const selectorTagId = 'awz-deals-selector-tag-' + this.escapeId(this.fieldName);
        const selectorTagNode = document.getElementById(selectorTagId);

        if (!selectorTagNode) {
            console.error('Selector tag container not found:', selectorTagId);
            return;
        }

        this.dealsData = JSON.parse(document.querySelector('input[name="'+this.escapeId(this.fieldName)+'-main"]').getAttribute('data-deals-data'));

        // Формируем список предвыбранных элементов в формате [entityId, id]
        const preselectedItems = Object.keys(this.dealsData).map(function(dealId) {
            return ['deal', dealId];
        }.bind(this));

        // Формируем список предзагруженных элементов с полными данными
        const preloadedItems = Object.keys(this.dealsData).map(function(dealId) {
            const deal = this.dealsData[dealId];
            return {
                id: dealId,
                entityId: 'deal',
                title: deal.title || 'Сделка #' + dealId,
                avatar: undefined,
                tabs: 'main'
            };
        }.bind(this));

        console.log(preselectedItems, preloadedItems);

        const field_name = this.escapeId(this.fieldName);

        const multiple = this.isMultiple;


        // Используем TagSelector для выбора сделок
        this.tagSelector = new BX.UI.EntitySelector.TagSelector({
            multiple: multiple,
            context: 'awz_deals_product_field',
            dialogOptions: {
                entities: [{
                    id: 'deal',
                    dynamicLoad: true,
                    dynamicSearch: true,
                    options: {
                        categoryId: 0
                    }
                }],
                preselectedItems: preselectedItems,
                preloadedItems: preloadedItems,
            },

            events: {
                'onAfterTagAdd': function(event) {
                    const tag = event.getData().tag;
                    const dialogItem = tag ? tag : null;

                    if (!dialogItem) {
                        console.error('Invalid dialog item:', tag);
                        return;
                    }

                    const dealId = dialogItem.getId();
                    const dealTitle = dialogItem.getTitle() || 'Сделка #' + dealId;

                    console.log('onAfterTagAdd:', dealId, dealTitle);

                    if (!instance.dealsData[dealId]) {
                        instance.dealsData[dealId] = {
                            id: dealId,
                            title: dealTitle,
                            products: [],
                            selectedProducts: []
                        };
                        instance.saveDealsData();
                        instance.renderDeals();
                    }

                    const event_2 = new Event('change', { bubbles: true });
                    document.querySelector('input[name="'+field_name+'-main"]').dispatchEvent(event_2);
                },
                'onAfterTagRemove': function(event) {
                    const tag = event.getData().tag;
                    const dialogItem = tag ? tag : null;

                    if (!dialogItem) {
                        console.error('Invalid dialog item in onAfterTagRemove');
                        return;
                    }

                    const dealId = dialogItem.getId();

                    console.log('onAfterTagRemove:', dealId);

                    if (instance.dealsData[dealId]) {
                        delete instance.dealsData[dealId];
                        instance.saveDealsData();
                        instance.renderDeals();
                    }

                    // Если все сделки удалены — обнуляем значение поля выбора
                    if (Object.keys(instance.dealsData).length === 0) {
                        instance.valueInput.value = '';
                        instance.valueInput.dataset.dealsData = '[]';
                        // Обнуляем значения hidden-инпутов, но не удаляем их
                        var existingInputs = document.querySelectorAll('input.awz-deals-product-multiple-value');
                        existingInputs.forEach(function(input) {
                            input.value = '';
                        });
                    }

                    const event_1 = new Event('change', { bubbles: true });
                    document.querySelector('input[name="'+field_name+'-main"]').dispatchEvent(event_1);
                }
            }
        });

        // Рендерим TagSelector в контейнер awz-deals-selector-tag-{fieldName}
        this.tagSelector.renderTo(selectorTagNode);
    };

    DealsProductField.prototype.openDealSelector = function() {
        if (this.tagSelector) {
            this.tagSelector.getDialog().show();
        } else {
            console.error('TagSelector not initialized');
        }
    };

    DealsProductField.prototype.renderDeals = function() {
        const dealIds = Object.keys(this.dealsData);

        if (dealIds.length === 0) {
            this.dealsList.innerHTML = '<div class="awz-empty-deals">' + this.escapeHtml(this.messages.noDeals) + '</div>';
            return;
        }

        this.dealsList.innerHTML = '';

        dealIds.forEach(function(dealId) {
            this.renderDealItem(dealId);
        }.bind(this));
    };

    DealsProductField.prototype.renderDealItem = function(dealId) {
        const deal = this.dealsData[dealId];
        const dealEl = document.createElement('div');
        dealEl.className = 'awz-deal-item';
        dealEl.dataset.dealId = dealId;

        const selectedCount = deal.selectedProducts ? deal.selectedProducts.length : 0;
        const totalCount = deal.products ? deal.products.length : 0;

        dealEl.innerHTML = `
            <div class="awz-deal-header">
                <div class="awz-deal-title awz-deal-title-link">${this.escapeHtml(deal.title || 'Сделка #' + dealId)}</div>
            </div>
            <div class="awz-deal-products-info">
                ${totalCount > 0 ? this.escapeHtml(this.messages.totalProducts) + ': ' + totalCount : this.escapeHtml(this.messages.noProducts)}
                ${selectedCount > 0 ? ' | ' + this.escapeHtml(this.messages.selectedProducts) + ': ' + selectedCount : ''}
            </div>
            <div class="awz-product-selector-wrapper" id="product-wrapper-${this.escapeAttribute(this.fieldName)}-${dealId}">
                <div class="awz-products-loading">${this.escapeHtml(this.messages.loading)}...</div>
            </div>
        `;

        this.dealsList.appendChild(dealEl);

        // Добавляем обработчик клика на название сделки для открытия в слайдере
        const dealTitleEl = dealEl.querySelector('.awz-deal-title-link');
        if (dealTitleEl) {
            dealTitleEl.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof BX.SidePanel !== 'undefined' && BX.SidePanel.Instance) {
                    BX.SidePanel.Instance.open(
                        '/crm/deal/details/' + dealId + '/',
                        {
                            allowChangeHistory: false,
                            cacheable: true
                        }
                    );
                }
            });
        }

        // Загружаем товары сделки
        this.loadDealProducts(dealId);
    };

    DealsProductField.prototype.loadDealProducts = function(dealId) {
        const wrapper = document.getElementById('product-wrapper-' + this.escapeId(this.fieldName) + '-' + dealId);
        if (!wrapper) return;

        const instance = this;

        BX.ajax({
            url: API_URL,
            method: 'POST',
            data: {
                dealId: dealId,
                sessid: BX.bitrix_sessid()
            },
            dataType: 'json',
            onsuccess: function(response) {
                if (response && response.data) {
                    instance.dealsData[dealId].products = response.data;
                    instance.saveDealsData();
                    instance.renderProductSelector(dealId, wrapper, response.data);
                } else {
                    wrapper.innerHTML = '<div class="awz-products-loading">' + instance.escapeHtml(instance.messages.loadError) + '</div>';
                }
            },
            onfailure: function() {
                wrapper.innerHTML = '<div class="awz-products-loading">' + instance.escapeHtml(instance.messages.loadError) + '</div>';
            }
        });
    };

    DealsProductField.prototype.renderProductSelector = function(dealId, wrapper, products) {
        if (!products || products.length === 0) {
            wrapper.innerHTML = '<div class="awz-products-loading"></div>';
            return;
        }

        const deal = this.dealsData[dealId];
        const selectedProducts = deal.selectedProducts || [];

        wrapper.innerHTML = '';

        // Заголовок
        const label = document.createElement('div');
        label.className = 'awz-products-label';
        label.textContent = this.messages.selectProducts;
        //wrapper.appendChild(label);

        // Контейнер для чекбоксов
        const productsList = document.createElement('div');
        productsList.className = 'awz-products-checkbox-list';

        products.forEach(function(product) {
            const productItem = document.createElement('div');
            productItem.className = 'awz-product-checkbox-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'awz-product-checkbox';
            checkbox.value = product.id;
            checkbox.dataset.fieldName = this.fieldName;
            checkbox.dataset.dealId = dealId;
            checkbox.checked = selectedProducts.includes(parseInt(product.id));

            const label = document.createElement('label');
            label.className = 'awz-product-checkbox-label';
            label.textContent = (product.name || this.messages.unnamedProduct) +
                               (product.price ? ' (' + this.formatPrice(product.price) + ')' : '') +
                               (product.quantity ? ' x ' + product.quantity + (product.measureName ? ' ' + product.measureName : '') : '');

            // Добавляем обработчик клика на label для переключения чекбокса
            label.addEventListener('click', function(e) {
                e.preventDefault();
                checkbox.checked = !checkbox.checked;
                // Инициируем событие change для чекбокса
                const event = new Event('change', { bubbles: true });
                checkbox.dispatchEvent(event);
            });

            productItem.appendChild(checkbox);
            productItem.appendChild(label);
            productsList.appendChild(productItem);
        }.bind(this));

        wrapper.appendChild(productsList);
    };

    DealsProductField.prototype.saveDealsData = function() {
        const jsonData = JSON.stringify(this.dealsData);
        this.valueInput.dataset.dealsData = jsonData;
        
        if (this.isMultiple) {
            // Для множественного поля: храним каждую сделку в отдельной строке
            this.saveMultipleFieldData();
        } else {
            // Для одиночного поля: храним все данные в одной строке
            this.saveOneFieldData();
        }
    };

    DealsProductField.prototype.saveOneFieldData = function() {
        // Очищаем существующие инпуты
        const existingInputs = document.querySelectorAll('input[name^="' + this.escapeAttribute(this.fieldName) + '["]');
        existingInputs.forEach(function(input) {
            input.value = '';
            input.remove();
        });

        // Также удаляем пустой hidden input (если был добавлен ранее)
        const emptyInput = this.container.querySelector('input.awz-deals-product-empty-value[name="' + this.escapeAttribute(this.fieldName) + '"]');
        if (emptyInput) {
            emptyInput.remove();
        }

        // Проверяем, есть ли выбранные товары (отмеченные чекбоксы)
        const dealIds = Object.keys(this.dealsData);
        let hasSelectedProducts = false;
        dealIds.forEach(function(dealId) {
            const deal = this.dealsData[dealId];
            if (deal.selectedProducts && deal.selectedProducts.length > 0) {
                hasSelectedProducts = true;
            }
        }.bind(this));

        // Создаем отдельные инпуты для каждой сделки
        if (!hasSelectedProducts) {
            // Если не выбрано ни одного товара, добавляем hidden input с пустым значением
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = this.fieldName;
            input.value = '';
            input.className = 'awz-deals-product-empty-value';
            this.container.querySelector('.awz-deals-selector').appendChild(input);
        } else {
            dealIds.forEach(function(dealId, index) {
                const dealData = this.dealsData[dealId];
                const dealJson = JSON.stringify({
                    [dealId]: dealData
                });

                // Создаем hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = this.fieldName;
                input.value = dealJson;
                input.className = 'awz-deals-product-multiple-value';
                this.container.querySelector('.awz-deals-selector').appendChild(input);
            }.bind(this));
        }

        // Основной инпут всегда пустой для множественного поля
        this.valueInput.value = '';
    };

    DealsProductField.prototype.saveMultipleFieldData = function() {
        // Очищаем существующие инпуты
        const existingInputs = document.querySelectorAll('input[name^="' + this.escapeAttribute(this.fieldName) + '["]');
        existingInputs.forEach(function(input) {
            input.value = '';
            input.remove();
        });

        // Также удаляем пустой hidden input (если был добавлен ранее)
        const emptyInput = this.container.querySelector('input.awz-deals-product-empty-value[name="' + this.escapeAttribute(this.fieldName) + '"]');
        if (emptyInput) {
            emptyInput.remove();
        }

        // Проверяем, есть ли выбранные товары (отмеченные чекбоксы)
        const dealIds = Object.keys(this.dealsData);
        let hasSelectedProducts = false;
        dealIds.forEach(function(dealId) {
            const deal = this.dealsData[dealId];
            if (deal.selectedProducts && deal.selectedProducts.length > 0) {
                hasSelectedProducts = true;
            }
        }.bind(this));

        // Создаем отдельные инпуты для каждой сделки
        if (!hasSelectedProducts) {
            // Если не выбрано ни одного товара, добавляем hidden input с пустым значением
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = this.fieldName;
            input.value = '';
            input.className = 'awz-deals-product-empty-value';
            this.container.querySelector('.awz-deals-selector').appendChild(input);
        } else {
            dealIds.forEach(function(dealId, index) {
                const dealData = this.dealsData[dealId];
                const dealJson = JSON.stringify({
                    [dealId]: dealData
                });

                // Создаем hidden input
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = this.fieldName + '[' + index + ']';
                input.value = dealJson;
                input.className = 'awz-deals-product-multiple-value';
                this.container.querySelector('.awz-deals-selector').appendChild(input);
            }.bind(this));
        }

        // Основной инпут всегда пустой для множественного поля
        this.valueInput.value = '';
    };

    DealsProductField.prototype.escapeHtml = function(str) {
        if (!str) return '';
        return str.replace(/&/g, '&')
                  .replace(/</g, '<')
                  .replace(/>/g, '>')
                  .replace(/"/g, '"')
                  .replace(/'/g, '&#039;');
    };

    DealsProductField.prototype.escapeAttribute = function(str) {
        if (!str) return '';
        return str.replace(/"/g, '"')
                  .replace(/'/g, '&#039;');
    };

    DealsProductField.prototype.escapeId = function(str) {
        if (!str) return '';
        return str.replace(/[^a-zA-Z0-9_-]/g, '_');
    };

    DealsProductField.prototype.formatPrice = function(price) {
        return parseFloat(price).toFixed(2);
    };

    // Получить экземпляр поля (кешированный)
    function getFieldInstance(fieldName) {
        return fieldsInstances[fieldName] || null;
    }

    // Создать новый экземпляр поля
    function createFieldInstance(fieldName) {
        const container = document.querySelector('.awz-deals-product-field-container[data-field-name="' + fieldName + '"]');
        if (container) {
            // Удаляем старый экземпляр если есть
            if (fieldsInstances[fieldName]) {
                delete fieldsInstances[fieldName];
            }
            fieldsInstances[fieldName] = new DealsProductField(fieldName, container);
            return fieldsInstances[fieldName];
        }
        return null;
    }

    // Публичные методы
    AwzDealsProductField.openDealSelector = function(fieldName) {
        const instance = getFieldInstance(fieldName);
        if (instance) {
            instance.openDealSelector();
        }
    };

    AwzDealsProductField.initField = function(fieldName) {
        // Каждый раз создаём новый экземпляр
        createFieldInstance(fieldName);
    };

    // Глобальный обработчик изменения выбора товаров
    document.addEventListener('change', function(e) {
        const checkbox = e.target.closest('.awz-product-checkbox');
        if (checkbox) {
            const fieldName = checkbox.dataset.fieldName;
            const dealId = checkbox.dataset.dealId;
            const instance = getFieldInstance(fieldName);
            if (instance && dealId) {
                const selectedIds = [];
                const selectedProductsData = [];
                const wrapper = document.getElementById('product-wrapper-' + instance.escapeId(fieldName) + '-' + dealId);
                if (wrapper) {
                    wrapper.querySelectorAll('.awz-product-checkbox:checked').forEach(function(chk) {
                        const productId = parseInt(chk.value, 10);
                        selectedIds.push(productId);
                        // Находим полные данные о товаре
                        const deal = instance.dealsData[dealId];
                        if (deal && deal.products) {
                            const productData = deal.products.find(function(p) {
                                return parseInt(p.id) === productId;
                            });
                            if (productData) {
                                selectedProductsData.push({
                                    id: productData.id,
                                    name: productData.name || '',
                                    price: productData.price || 0,
                                    quantity: productData.quantity || 0,
                                    measureName: productData.measureName || '',
                                    productId: productData.productId || 0
                                });
                            }
                        }
                    });
                }
                // Обновляем информацию о выбранных товарах с полными данными
                if (instance.dealsData[dealId]) {
                    instance.dealsData[dealId].selectedProducts = selectedIds;
                    instance.dealsData[dealId].selectedProductsData = selectedProductsData;
                    instance.saveDealsData();
                    // Обновляем информацию о выбранных товарах без перерендеринга всего списка
                    const dealInfoEl = wrapper.previousElementSibling;
                    if (dealInfoEl && dealInfoEl.classList.contains('awz-deal-products-info')) {
                        const deal = instance.dealsData[dealId];
                        const selectedCount = selectedIds.length;
                        const totalCount = deal.products ? deal.products.length : 0;
                        dealInfoEl.innerHTML = totalCount > 0 ? instance.escapeHtml(instance.messages.totalProducts) + ': ' + totalCount + (selectedCount > 0 ? ' | ' + instance.escapeHtml(instance.messages.selectedProducts) + ': ' + selectedCount : '') : instance.escapeHtml(instance.messages.noProducts);
                    }
                }
            }
        }
    });

})();