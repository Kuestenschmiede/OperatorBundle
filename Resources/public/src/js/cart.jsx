
// Todo Felder zur Bearbeitung mit Validierung (Guthaben)
// Todo API Aufruf bei erfolgreichem Ausfüllen der Felder
// Todo Update UI bei erfolgreichem Ausfüllen der felder
// Todo Bei Fehler Fehlermeldung ausgeben

import React, {Component} from "react";
import ReactDOM from "react-dom";

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
              (<div key={0} className={"c4g-cart-vendor-article"}>
                <form action={""} className={"c4g-cart-vendor-article-form"}>
                  <span key={0} className={"c4g-cart-vendor-article-name"}>
                    {article.name}
                  </span>
                  <span key={1} className={"c4g-cart-vendor-article-price-per-unit"}>
                    {this.numberFormat(article.pricePerUnit)}
                  </span>
                  <input key={2} type="number" className={"c4g-cart-vendor-article-amount"} step={1} min={1}
                         onInput={this.updateAmount.bind(this, vendors.length, articles.length)}
                         defaultValue={article.amount}/>
                  <span key={0} className={"c4g-cart-vendor-article-total-price"}>
                    {this.numberFormat(
                      article.pricePerUnit * (article.amount || 0)
                    )}
                  </span>
                </form>
              </div>));
          }, this);
        }
        vendors.push(
          <div className={"c4g-cart-vendor"}>
            <span className={"c4g-cart-vendor"}>{vendor.name}</span>
            <div className={"c4g-cart-vendor-article-list"}>
              <div className={"c4g-cart-vendor-article-header"}>
                <span className={"c4g-cart-vendor-article-name"}>Artikel</span>
                <span className={"c4g-cart-vendor-article-price-per-unit"}>Einzelpreis</span>
                <span className={"c4g-cart-vendor-article-amount"}>Anzahl</span>
                <span className={"c4g-cart-vendor-article-total-price"}>Gesamtpreis</span>
                {articles}
                <div className={"c4g-cart-vendor-total"}>
                  <span className={"c4g-cart-vendor-total-label"}>
                    {this.props.totalPriceLabel}
                  </span>
                  <span className={"c4g-cart-vendor-total-price"}>
                    {this.numberFormat(vendorTotal)}
                  </span>
                </div>
                <div className={"c4g-cart-vendor-checkout"}>
                  <button className={"c4g-cart-vendor-checkout-button"}>
                    <span className={"c4g-cart-vendor-checkout-button-text"}>
                      {this.props.checkoutButtonText}
                    </span>
                  </button>
                </div>
              </div>
            </div>
          </div>)
      }, this);
    }

    return (<div>
      <button className={"c4g-cart-toggle-button"} onClick={this.toggle}>
        {this.props.toggleButtonText}
      </button>
      <div className={"c4g-cart" + (this.state.hidden ? " " + this.props.hiddenClass : "")}>
        {vendors}
      </div>
    </div>);
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