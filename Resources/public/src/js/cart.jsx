import React, {Component} from "react";
import ReactDOM from "react-dom";

class Slider extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      value: this.props.defaultValue
    };

    this.timeout = null;

    this.onChange = this.onChange.bind(this);
    Slider.instances = (Slider.instances || 0) + 1;
  }

  onChange(event) {
    this.setState({value: event.target.value});
    clearTimeout(this.timeout);
    this.timeout = setTimeout(() => {
      this.props.updateValue(event);
    }, 500);
  }

  textFormat(value) {
    if (this.props.text && typeof this.props.text.replace === 'function') {
      return this.props.text.replace(/%s/g, value);
    } else {
      return value;
    }
  }

  render() {
    try {
      let id = 'cart__article-option-slider-' + Slider.instances;
      let left = parseFloat(this.props.left);
      left = (100.00 - (2 * left)) / (this.props.max - this.props.min) * (this.state.value - this.props.min) + left;

      return (
        <div className={"cart__article-option-slider"}>
          <label htmlFor={id}>
            {this.props.label}
          </label>
          <div className={'cart__article-option-slider-inner'}>
            <div className={'cart__article-option-slider-value-wrapper'}
                 style={{left: String(left) + '%'}}>
            <span className={'cart__article-option-slider-value'}>
              {this.textFormat(this.state.value)}
            </span>
            </div>
            <span className={'cart__article-option-slider-min'}>{this.textFormat(this.props.min)}</span>
            <input id={id}
                   type={'range'}
                   min={this.props.min}
                   max={this.props.max}
                   step={this.props.interval}
                   value={this.state.value}
                   onChange={this.onChange}
                   className={'cart__article-option-slider-input'}
                   name={this.props.name}/>
            <span className={'cart__article-option-slider-max'}>{this.textFormat(this.props.max)}</span>
          </div>
        </div>
      );
    } catch (e) {
      console.log(e);
      return null;
    }
  }
}

class Select extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      value: this.props.defaultValue
    };

    this.timeout = null;

    this.onChange = this.onChange.bind(this);
    Select.instances = (Select.instances || 0) + 1;
  }

  onChange(event) {
    this.setState({value: event.target.value});
    clearTimeout(this.timeout);
    this.props.updateValue(event);
  }

  textFormat(value) {
    if (this.props.text && typeof this.props.text.replace === 'function') {
      return this.props.text.replace(/%s/g, value);
    } else {
      return value;
    }
  }

  render() {
    try {
      let id = 'cart__article-option-select-' + Select.instances;

      return (
        <div className={"cart__article-option-select"}>
          <label htmlFor={id}>
            {this.props.label}
          </label>
          <select id={id}
                  value={this.state.value}
                  onChange={this.onChange}
                  className={'cart__article-option-select'}
                  name={this.props.name}>
            {
              (() => {
                let options = [];
                this.props.options.forEach((opt) => {
                  options.push(<option value={opt.value}>{this.textFormat(opt.label)}</option>);
                }, this);
                return options;
              })()
            }
          </select>
        </div>
      );
    } catch (e) {
      console.log(e);
      return null;
    }
  }
}

class Option extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
    try {
      return (
        <div className="cart__article-option">
          {
            (() => {
              switch (this.props.type) {
                case 'slider':
                  return <Slider label={this.props.label}
                                 name={this.props.name}
                                 min={this.props.min}
                                 max={this.props.max}
                                 defaultValue={this.props.defaultValue}
                                 interval={this.props.interval}
                                 left={16.75}
                                 updateValue={this.props.updateValue}/>

                case 'select':
                  return <Select label={this.props.label}
                                 text={'%s,00 EUR'}
                                 name={this.props.name}
                                 options={this.props.options}
                                 defaultValue={this.props.defaultValue}
                                 updateValue={this.props.updateValue}/>
                default:
                  return null;
              }
            })()
          }
        </div>
      );
    } catch (e) {
      console.log(e);
      return null;
    }
  }
}

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
      remove: 'Entfernen',
      options : {
        pricePerUnit: 'Guthaben'
      }
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
              <div className="cart__article-row--one">
                <div className="cart__article-info">
                  {
                    this.props.article.image &&
                    this.props.article.image.src &&
                    <div className="cart__article-image">
                      <img src={this.props.article.image.src}
                           alt={this.props.article.image.alt}
                           height={80}/>
                    </div>
                  }
                  <div className="cart__article-detail">
                    <div className="cart__article-name">
                      <a href={this.props.article.href}
                         target={"_blank"}
                         rel={"noopener norefferer"}>
                        {this.props.article.name}
                      </a>
                    </div>
                    <div className="cart__article-amount">
                      <label>
                    <span>
                      {this.int.amount}
                    </span>
                        <span>
                      <input type={"number"}
                             name={"amount"}
                             step={1}
                             min={1}
                             max={10}
                             onInput={this.props.updateValue.bind(this, this.props.vendorKey, this.props.articleKey)}
                             defaultValue={this.props.article.amount}/>
                    </span>
                      </label>
                    </div>
                    <div className="cart__article-action">
                      {
                        this.props.article.options &&
                        this.props.article.options.length > 0 &&
                        <button className="btn btn-sm btn-outline-dark"
                                data-toggle="collapse"
                                data-target={"#article" + this.props.article.articleId}
                                aria-expanded="false"
                                aria-controls={"article" + this.props.article.articleId}>
                          {this.int.moreOptions}
                        </button>
                      }
                      <button className="btn btn-sm btn-danger"
                              onClick={() => {
                                  this.props.removeArticle(
                                    this.props.vendorKey,
                                    this.props.articleKey,
                                    {
                                      target: {
                                        value : 0,
                                        name: 'amount'
                                      },
                                      article: this.props.article
                                    }
                                  );
                                }
                              }>
                        {this.int.remove}
                      </button>
                    </div>
                  </div>
                </div>
                <div className="cart__article-price">
                  <div className="article-price__one-piece">
                    {this.int.priceSingle + ' ' + this.numberFormat(this.props.article.pricePerUnit)}
                    <br/>
                    <small className="text-muted">
                      {this.textFormat(this.int.tax, this.props.article.tax)}
                    </small>
                  </div>
                  <div className="article-price__sum">
                    {this.int.priceTotal + ' ' + this.numberFormat(this.props.article.pricePerUnit * (this.props.article.amount || 0))}
                  </div>
                </div>
              </div>
              {
                this.props.article.options &&
                this.props.article.options.length > 0 &&
                <div className="cart__article-row--two">
                  <div id={"article" + this.props.article.articleId} className={'cart__article-more collapse mt-3'}>
                    {
                      (() => {
                        let options = [];
                        this.props.article.options.forEach(function(element, index) {
                          options.push(
                            <Option key={index}
                                    label={this.int.options[element.name]}
                                    updateValue={
                                      this.props.updateValue.bind(
                                        this,
                                        this.props.vendorKey,
                                        this.props.articleKey
                                      )
                                    }
                                    defaultValue={this.props.article[element.name]}
                                    {...element}/>
                          );
                        }, this);
                        return options;
                      })()
                    }
                  </div>
                </div>
              }
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

    this.updateValue = this.updateValue.bind(this);
    this.removeArticle = this.removeArticle.bind(this);
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

  updateValue(vendorKey, articleKey, event) {
    let value = event.target.value;
    let vendors = this.state.vendors;
    vendors[vendorKey].articles[articleKey][event.target.name] = value;
    this.setState({vendors: vendors});
    if (Number.isInteger(parseInt(value))) {
      let data = new FormData();
      data.set(event.target.name, value);
      data.set('childId', vendors[vendorKey].articles[articleKey].childId);
      data.set('articleId', vendors[vendorKey].articles[articleKey].articleId);
      let promise = fetch(this.configCartUrl, {
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
      });
      promise.then((response) => {
        if (!response.ok) {
          location.reload();
        }
      });
      return promise;
    }
    return null;
  }

  removeArticle(vendorKey, articleKey, event) {
    let promise = this.updateValue(vendorKey, articleKey, event);
    if (promise !== null) {
      promise.then((response) => {
        if (response.ok) {
          const vendors = this.state.vendors;
          const newVendors = [];
          vendors.forEach((vendor, index) => {
            if (index !== vendorKey) {
              newVendors.push(vendor);
            } else {
              const articles = vendor.articles;
              const newArticles = [];
              articles.forEach((article, aIndex) => {
                if (article.articleId !== event.article.articleId) {
                  newArticles.push(article);
                }
              });
              vendor.articles = newArticles || [];
              if (vendor.articles.length) {
                newVendors.push(vendor);
              }
            }
          });
          this.setState({vendors: newVendors});
        } else {
          // todo fehlermeldung
        }
      });
    }
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
        this.setState({vendors: []});
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
                           updateValue={this.updateValue}
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
                <a href={vendor.elementLink} target={"_blank"} rel={'noopener noreferrer'}>
                  {vendor.name}
                </a>
              </div>

              <div className="card-header__image">
                <img src={vendor.image.src}
                     alt={vendor.image.alt}
                     height={80} />
              </div>

            </div>
            <div className={"card-body"}>
              <div className={"cart__article-row mb-3"}>
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
                <button type={"button"} className={"btn btn-danger"} data-toggle="modal" data-target="#cleanListModal">
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
      <div className={'modal fade'}
           id={'cleanListModal'}
           tabIndex='-1'
           aria-labelledby='cleanListModalLabel'
           aria-hidden={true}>
        <div className={'modal-dialog modal-dialog-centered'}>
          <div className={'modal-content'}>
            <div className={'modal-header'}>
              <h5 className={'modal-title'} id={'cleanListModalLabel'}>{this.state.removeAllSanityCheckTitle}</h5>
              <button type={'button'} className={'close'} aria-label={this.int.cancel} data-dismiss="modal">
                <span aria-hidden={true}>&times;</span>
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
