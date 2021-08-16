
// Todo Felder zur Bearbeitung mit Validierung (Guthaben)
// Todo API Aufruf bei erfolgreichem Ausfüllen der Felder
// Todo Update UI bei erfolgreichem Ausfüllen der felder
// Todo Bei Fehler Fehlermeldung ausgeben

class Cart extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      hidden: true,
      vendors: this.props.vendors
    };

    this.toggle = this.toggle.bind(this);
  }

  toggle() {
    this.setState({hidden: !this.state.hidden});
  }

  numberFormat(value) {
    value = parseFloat(value);
    if (value > 0) {
      value = value.toFixed(2);
      return value.replace('.',',') + '€';
    } else {
      return '';
    }
  }

  updateAmount(vendorKey, articleKey, event) {
    let value = event.target.value;
    let vendors = this.state.vendors;
    vendors[vendorKey].articles[articleKey].amount = value;
    this.setState({vendors: vendors});
    if (Number.isInteger(parseInt(value))) {
      let xhr = new XMLHttpRequest();
      xhr.open('POST', this.props.configCartUrl, true);
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      let onReady = function(xhr) {
        if (xhr.readyState === 4) {
          if (xhr.status !== 200) {
            console.log('Config Cart returned ' + xhr.status);
          }
        }
      };
      onReady = onReady.bind(this, xhr);
      xhr.onreadystatechange = onReady;
      let formData = new FormData();
      formData.set('amount', value);
      formData.set('childId', vendors[vendorKey].articles[articleKey].childId);
      formData.set('articleId', vendors[vendorKey].articles[articleKey].articleId);
      xhr.send(formData);
    }
  }

  render() {
    const e = React.createElement;
    let vendors = [];
    let vendorTotal = 0;

    if (typeof this.state.vendors.forEach === 'function') {
      this.state.vendors.forEach(function (vendor) {
        let articles = [];
        vendorTotal = 0;
        if (typeof vendor.articles.forEach === 'function') {
          vendor.articles.forEach(function (article) {
            vendorTotal += article.pricePerUnit * article.amount;
            articles.push(
              e(
                'div',
                {key: (articles.length + 3), className: 'c4g-cart-vendor-article'},
                [
                  e(
                    'form',
                    {key: 0, className: 'c4g-cart-vendor-article-form'},
                    [
                      e(
                        'span',
                        {key: 0, className: 'c4g-cart-vendor-article-name'},
                        article.name
                      ),
                      e(
                        'span',
                        {key: 1, className: 'c4g-cart-vendor-article-price-per-unit'},
                        this.numberFormat(article.pricePerUnit)
                      ),
                      e(
                        'input',
                        {
                          key: 2,
                          className: 'c4g-cart-vendor-article-amount',
                          type: 'number',
                          step: '1',
                          min: '1',
                          value: article.amount,
                          onInput: this.updateAmount.bind(this, vendors.length, articles.length)
                        }
                      ),
                      e(
                        'span',
                        {key: 3, className: 'c4g-cart-vendor-article-total-price'},
                        this.numberFormat(
                          article.pricePerUnit * (article.amount || 0)
                        )
                      )
                    ]
                  )
                ]
              )
            );
          }, this);
        }
        vendors.push(
          e(
            'div',
            {key: vendors.length, className: 'c4g-cart-vendor'},
            [
              e(
                'span',
                {key: 0, className: 'c4g-cart-vendor'},
                vendor.name
              ),
              e(
                'div',
                {key: 1, className: 'c4g-cart-vendor-article-list'},
                [
                  e(
                    'div',
                    {key: 0, className: 'c4g-cart-vendor-article-header'},
                    [
                      e(
                        'span',
                        {key: 0, className: 'c4g-cart-vendor-article-name'},
                        'Artikel'
                      ),
                      e(
                        'span',
                        {key: 1, className: 'c4g-cart-vendor-article-price-per-unit'},
                        'Einzelpreis'
                      ),
                      e(
                        'span',
                        {key: 2, className: 'c4g-cart-vendor-article-amount'},
                        'Anzahl'
                      ),
                      e(
                        'span',
                        {key: 3, className: 'c4g-cart-vendor-article-total-price'},
                        'Gesamtpreis'
                      ),
                    ]
                  )
                ].concat(articles).concat([
                  e(
                    'div',
                    {key: 1, className: 'c4g-cart-vendor-total'},
                    [
                      e(
                        'span',
                        {key: 0, className: 'c4g-cart-vendor-total-label'},
                        this.props.totalPriceLabel
                      ),
                      e(
                        'span',
                        {key: 1, className: 'c4g-cart-vendor-total-price'},
                        this.numberFormat(vendorTotal)
                      )
                    ]
                  ),
                  e(
                    'div',
                    {key: 2, className: 'c4g-cart-vendor-checkout'},
                    [
                      e(
                        'button',
                        {key: 0, className: 'c4g-cart-vendor-checkout-button'},
                        [
                          e(
                            'span',
                            {key: 0, className: 'c4g-cart-vendor-checkout-button-text'},
                            this.props.checkoutButtonText
                          )
                        ]
                      )
                    ]
                  )
                ])
              )
            ]
          )
        );
      }, this);
    }

    return e(
      'div',
      {key: 0},
      [
        e(
          'button',
          {key: 0, className: 'c4g-cart-toggle-button', onClick: this.toggle},
          this.props.toggleButtonText
        ),
        e(
          'div',
          {key: 1, className: 'c4g-cart' + (this.state.hidden ? ' ' + this.props.hiddenClass : '')},
          vendors
        )
      ]
    );
  }


  static fetch() {
    let carts = document.getElementsByClassName('c4g-cart-wrapper');
    let i = 0;
    while (i < carts.length) {
      let cart = carts[i];
      let xhr = new XMLHttpRequest();
      xhr.open('GET', cart.dataset.getCartUrl, true);
      xhr.withCredentials = true;
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      let onReady = function(xhr, cart) {
        if (xhr.readyState === 4) {
          if (xhr.status === 200 && xhr.responseText !== null) {
            let json = JSON.parse(xhr.responseText);
            ReactDOM.render(React.createElement(Cart, json), cart);
          }
        }
      };
      onReady = onReady.bind(this, xhr, cart);
      xhr.onreadystatechange = onReady;
      xhr.send();
      i += 1;
    }
  }

}

Cart.fetch();