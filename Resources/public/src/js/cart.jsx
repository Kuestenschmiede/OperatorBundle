
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

    this.getCartUrl = this.props.getCartUrl;
    this.addCartUrl = this.props.addCartUrl;
    this.removeCartUrl = this.props.removeCartUrl;
    this.configCartUrl = this.props.configCartUrl;
    this.toggleClass = this.props.toggleClass;
    this.toggleButtonText = this.props.toggleButtonText;

    this.toggle = this.toggle.bind(this);
    this.removeArticle = this.removeArticle.bind(this);
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
      let data = {
        amount: value,
        childId: vendors[vendorKey].articles[articleKey].childId
      };
      fetch(this.configCartUrl, {
        method: 'POST',
        mode: 'cors',
        cache: 'no-cache',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With' : 'XMLHttpRequest'
        },
        redirect: 'follow',
        referrerPolicy: 'no-referrer',
        body: JSON.stringify(data)
      }).then(response => response.json()).then((responseData) => {
        console.log('config cart returned');
        console.log(responseData);
      });
    }
  }

  removeArticle(articleData, articleIndex, vendorIndex) {
    fetch(this.removeCartUrl, {
      method: 'POST',
      mode: 'cors',
      cache: 'no-cache',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With' : 'XMLHttpRequest'
      },
      redirect: 'follow',
      referrerPolicy: 'no-referrer',
      body: JSON.stringify({
        articleData: articleData,
      })
    }).then(response => response.json())
      .then((data) => {
        if (data.success) {
          const vendors = this.state.vendors;
          const newVendors = [];
          vendors.forEach((vendor, index) => {
            if (index !== vendorIndex) {
              newVendors.push(vendor);
            } else {
              const articles = vendor.articles;
              const newArticles = [];
              articles.forEach((article, aIndex) => {
                if (article.articleId !== articleData.articleId) {
                  newArticles.push(article);
                }
              });
              vendor.articles = newArticles;
              newVendors.push(vendor);
            }
          });
          this.setState({vendors: newVendors});
        } else {
          // todo fehlermeldung
        }
    });
  }

  render() {
    let vendors = [];
    let vendorTotal = 0;

    if (typeof this.state.vendors.forEach === 'function') {
      this.state.vendors.forEach(function (vendor, vIndex) {
        let articles = [];
        vendorTotal = 0;
        if (typeof vendor.articles.forEach === 'function') {
          vendor.articles.forEach(function (article, index) {
            vendorTotal += article.pricePerUnit * article.amount;
            articles.push(
              (<div key={index} className={"c4g-cart-vendor-article"}>
                <form key={index} action={""} className={"c4g-cart-vendor-article-form"}>
                  <span key={0} className={"c4g-cart-vendor-article-name"}>
                    {article.name}
                  </span>
                  <span key={1} className={"c4g-cart-vendor-article-price-per-unit"}>
                    {this.numberFormat(article.pricePerUnit)}
                  </span>
                  <input key={2} type="number" className={"c4g-cart-vendor-article-amount"} step={1} min={1}
                         onInput={this.updateAmount.bind(this, vendors.length, articles.length)}
                         defaultValue={article.amount}/>
                  <span key={3} className={"c4g-cart-vendor-article-total-price"}>
                    {this.numberFormat(
                      article.pricePerUnit * (article.amount || 0)
                    )}
                  </span>
                </form>
                <button className={"btn btn-remove btn-primary"} onClick={() => {this.removeArticle(article, index, vIndex)}}>Löschen</button>
              </div>));
          }, this);
        }
        vendors.push(
          <div key={vIndex} className={"c4g-cart-vendor"}>
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
}

jQuery(document).ready(() => {
  let carts = document.getElementsByClassName('c4g-cart-wrapper');
  const cart = carts[0];
  if (cart) {
    let cartUrlData = cart.dataset;
    fetch(cartUrlData.getCartUrl, {
      headers: {
        'X-Requested-With' : 'XMLHttpRequest'
      }
    })
      .then(response => response.json())
      .then((data) => {
        let cartData = Object.assign(data, cartUrlData);
        ReactDOM.render(
          <Cart {...cartData} />,
          cart
        );
    });
  }
});
