import React, {Component} from "react";
import ReactDOM from "react-dom";

class Article extends React.Component {
  constructor(props) {
    super(props);

    // Wie Internationalisierung?
    this.int = {
      amount: 'Anzahl:',
      priceSingle: 'Einzelpreis:',
      priceTotal: 'Gesamtpreis',
      tax: 'inkl. %s% MwSt.',
      moreOptions: 'Weitere Optionen',
      remove: 'Entfernen'
    };
  }

  numberFormat(value) {
    value = parseFloat(value);
    if (value > 0) {
      value = value.toFixed(2);
      return value.replace('.',',') + ' EUR';
    } else {
      return '';
    }
  }

  textFormat(text, value) {
    return text.replace(/%s/g, value);
  }

  render() {
    try {
      return (
        <div key={this.props.index} className={"cart__article-row mb-3"}>
          <div className="card">
            <div className="card-body">
              <div className="cart__product-row--one">
                <div className="cart__product-info">
                  <div className="cart__product-image">
                    <img src={this.props.article.image.src}
                         alt={this.props.article.image.alt}
                         height={80}/>
                  </div>
                  <div className="cart__product-detail">
                    <div className="cart__product-name">
                      <a href={this.props.article.href}
                         target={"_blank"}>
                        {this.props.article.name}
                      </a>
                    </div>
                    <div className="cart__product-amount">
                      <label>
                    <span>
                      {this.int.amount}
                    </span>
                        <span>
                      <input type={"number"}
                             step={1}
                             min={1}
                             onInput={this.props.updateAmount.bind(this, this.props.vendorKey, this.props.articleKey)}
                             defaultValue={this.props.article.amount}/>
                    </span>
                      </label>
                    </div>
                    <div className="cart__product-action">
                      <button className="btn btn-sm btn-outline-dark"
                              data-toggle="collapse"
                              data-target={"#article" + this.props.article.articleId}
                              aria-expanded="false"
                              aria-controls={"article" + this.props.article.articleId}>
                        {this.int.moreOptions}
                      </button>
                      <button className="btn btn-sm btn-danger"
                              onClick={() => {
                                this.props.removeArticle(this.props.article, this.props.index, this.props.vIndex)
                              }}>
                        {this.int.remove}
                      </button>
                    </div>
                  </div>
                </div>
                <div className="cart__product-price">
                  <div className="product-price__one-piece">
                    {this.int.priceSingle + ' ' + this.numberFormat(this.props.article.pricePerUnit)}
                    <br/>
                    <small className="text-muted">
                      {this.textFormat(this.int.tax, this.props.article.tax)}
                    </small>
                  </div>
                  <div className="product-price__sum">
                    {this.int.priceTotal + ' ' + this.numberFormat(this.props.article.pricePerUnit * (this.props.article.amount || 0))}
                  </div>
                </div>
              </div>
              <div className="cart__product-row--two">
                <div id={"article" + this.props.article.articleId} className={'cart__product-more collapse mt-3'}>
                </div>
              </div>
            </div>
          </div>
        </div>);
    } catch (e) {
      console.log(e);
      return null;
    }
  }
}

class Cart extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      vendors: this.props.vendors,
      modalOpen: this.props.modalOpen
    };

    this.getCartUrl = this.props.getCartUrl;
    this.addCartUrl = this.props.addCartUrl;
    this.removeCartUrl = this.props.removeCartUrl;
    this.configCartUrl = this.props.configCartUrl;
    this.toggleClass = this.props.toggleClass;
    this.toggleButtonText = this.props.toggleButtonText;

    this.updateAmount = this.updateAmount.bind(this);
    this.removeArticle = this.removeArticle.bind(this);
    this.toggleModal = this.toggleModal.bind(this);
    this.removeAllArticles = this.removeAllArticles.bind(this);

    // Wie Internationalisierung?
    this.int = {
      clearCart: 'Warenkorb leeren',
      toPayment: 'Zum Bezahlprozess',
      cancel: 'Abbrechen',
      confirm: 'Bestätigen',
      removeAllSanityCheckTitle: 'Warenkorb leeren?',
      removeAllSanityCheckText: 'Wenn Sie bestätigen, wird Ihre Liste im Warenkorb unwiderruflich gelöscht.'
    };
  }

  updateAmount(vendorKey, articleKey, event) {
    let value = event.target.value;
    let vendors = this.state.vendors;
    vendors[vendorKey].articles[articleKey].amount = value;
    this.setState({vendors: vendors});
    if (Number.isInteger(parseInt(value))) {
      let data = new FormData();
      data.set('amount', value);
      data.set('childId', vendors[vendorKey].articles[articleKey].childId);
      data.set('articleId', vendors[vendorKey].articles[articleKey].articleId);
      fetch(this.configCartUrl, {
        method: 'POST',
        mode: 'cors',
        cache: 'no-cache',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With' : 'XMLHttpRequest'
        },
        redirect: 'follow',
        referrerPolicy: 'no-referrer',
        body: data
      }).then(response => response.json()).then((responseData) => {

      });
    }
  }

  removeArticle(articleData, articleIndex, vendorIndex) {
    let data = new FormData();
    for (let key in articleData ) {
      data.append(key, articleData[key]);
    }
    fetch(this.removeCartUrl, {
      method: 'POST',
      mode: 'cors',
      cache: 'no-cache',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With' : 'XMLHttpRequest'
      },
      redirect: 'follow',
      referrerPolicy: 'no-referrer',
      body: data
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

  toggleModal() {
    console.log(this.state.modalOpen);
    this.setState({modalOpen: !this.state.modalOpen});
  }

  removeAllArticles() {
    fetch(this.props.removeAllCartUrl, {
      method: 'POST',
      mode: 'cors',
      cache: 'no-cache',
      credentials: 'same-origin',
      headers: {
        'X-Requested-With' : 'XMLHttpRequest'
      },
      redirect: 'follow',
      referrerPolicy: 'no-referrer'
    }).then(response => {
      if (response.ok) {
        this.setState({vendors: [], modalOpen: false});
      }
    });
  }

  render() {
    let vendors = [];

    if (typeof this.state.vendors.forEach === 'function') {
      if (this.state.vendors.length === 0) {
        return (<p dangerouslySetInnerHTML={{__html: this.props.cartNoItemsText}}></p>);
      }
      this.state.vendors.forEach(function (vendor, vIndex) {
        let articles = [];
        if (typeof vendor.articles.forEach === 'function') {
          vendor.articles.forEach(function (article, index) {
            try {
              articles.push(
                (
                  <Article key={index}
                           index={index}
                           vIndex={vIndex}
                           article={article}
                           articleKey={articles.length}
                           vendorKey={vendors.length}
                           updateAmount={this.updateAmount}
                           removeArticle={this.removeArticle}>
                  </Article>
                )
              );
            } catch (e) {
              console.log(e);
            }
          }, this);
        }
        vendors.push(
          <div key={vIndex} className={"card vendor-card mb-4"}>
            <div className={"card-header"}>
              <div className={"card-header__title"}>
                {vendor.name}
              </div>

              <div className="card-header__image">
                <img src={vendor.image.src}
                     alt={vendor.image.alt}
                     height={80} />
              </div>

            </div>
            <div className={"card-body"}>
              <div className={"cart__product-row mb-3"}>
                <div className="card">
                  <div className="card-body">
                    {articles}
                  </div>
                </div>
              </div>
            </div>
          </div>);
      }, this);
    }

    return (<div className={"container"}>
      <div className={"row"}>
        <div className={"col-12"}>
          {vendors}
        </div>
      </div>
      <div className={"row"}>
        <div className={"col-12"}>
          <div className={"card"}>
            <div className={"card-body"}>
              <div className={"text-right"}>
                <button type={"button"} className={"btn btn-danger"} onClick={this.toggleModal} data-toggle="modal" data-target="#cleanListModal">
                  {this.int.clearCart}
                </button>
                <a className={"btn btn-primary"} href={this.props.cartPaymentUrl}>
                  {this.int.toPayment}
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div className={this.state.modalOpen ? 'modal' : 'modal fade'}
           id={'cleanListModal'}
           tabIndex='-1'
           aria-labelledby='cleanListModalLabel'
           aria-hidden={this.state.modalOpen}>
        <div className={'modal-dialog modal-dialog-centered'}>
          <div className={'modal-content'}>
            <div className={'modal-header'}>
              <h5 className={'modal-title'} id={'cleanListModalLabel'}>{this.state.removeAllSanityCheckTitle}</h5>
              <button type={'button'} className={'close'} aria-label={this.int.cancel} onClick={this.toggleModal} data-dismiss="modal">
                <span aria-hidden={this.state.modalOpen}>&times;</span>
              </button>
            </div>
            <div className={'modal-body'}>
              {this.int.removeAllSanityCheckText}
            </div>
            <div className='modal-footer'>
              <button type={'button'} className={'btn btn-secondary'} data-dismiss="modal">
                {this.int.cancel}
              </button>
              <button type={'button'} className={'btn btn-danger'} onClick={this.removeAllArticles} data-dismiss="modal">
                {this.int.confirm}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>);
  }
}

jQuery(document).ready(() => {
  let carts = document.getElementsByClassName('c4g-cart-wrapper');
  const cart = carts[0];
  if (cart) {
    let cartUrlData = cart.dataset;
    if (cartUrlData.getCartUrl) {
      fetch(cartUrlData.getCartUrl, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
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
  }
});
