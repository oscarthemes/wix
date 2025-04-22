import { getElement, updateElement } from 'wix-custom-element';

const projects = {
  beer: {
    text: "SEE THE beer CASE STUDY",
    href: "https://www.brandbureau.com/project/beer",
    img: "https://www.brandbureau.com/wp-content/themes/brandbureau/assets/image/beer.jpg"
  },
  dispensary: {
    text: "SEE THE rise CASE STUDY",
    href: "https://www.brandbureau.com/project/rise",
    img: "https://www.brandbureau.com/wp-content/themes/brandbureau/assets/image/dispensary.jpg"
  },
  resort: {
    text: "SEE THE resort CASE STUDY",
    href: "https://www.brandbureau.com/project/resort",
    img: "https://www.brandbureau.com/wp-content/themes/brandbureau/assets/image/resort.jpg"
  }
};

const servicesData = [
  { _id: "1", service: "Competitive Analysis", categories: ["beer", "resort", "dispensary"] },
  { _id: "2", service: "Market Research", categories: ["beer", "resort", "dispensary"] },
  { _id: "3", service: "Brand & Design Audits", categories: ["dispensary"] },
  { _id: "4", service: "Brand Architecture", categories: ["beer", "resort"] },
  { _id: "5", service: "F&B Master Planning", categories: ["resort"] },
  { _id: "6", service: "Culinary & Beverage Development", categories: ["beer"] },
  { _id: "7", service: "Environmental Graphics", categories: ["dispensary"] },
  { _id: "8", service: "Digital Art Direction", categories: ["resort"] }
];

class ServicesSelector extends HTMLElement {
  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
    this.currentSelection = 'dispensary';
  }

  connectedCallback() {
    this.render();
    this.setupEventListeners();
    this.updateCaseStudy(this.currentSelection);
    this.updateRepeater(this.currentSelection);
  }

  render() {
    this.shadowRoot.innerHTML = `
      <style>
        .selector-wrapper {
          display: flex;
          flex-direction: column;
          gap: 20px;
          max-width: 800px;
          margin: 0 auto;
          padding: 20px;
          font-family: Arial, sans-serif;
        }
        .custom-select {
          position: relative;
          width: 300px;
        }
        .custom-select__option {
          width: 100%;
          padding: 10px;
          background: #f5f5f5;
          border: 1px solid #ccc;
          cursor: pointer;
          text-align: left;
          font-size: 16px;
        }
        .custom-select__option--value {
          background: #fff;
        }
        .custom-select__dropdown {
          display: none;
          position: absolute;
          top: 100%;
          width: 100%;
          background: #fff;
          border: 1px solid #ccc;
          z-index: 10;
        }
        .custom-select__dropdown.show {
          display: block;
        }
        .case-study {
          display: flex;
          flex-direction: column;
          gap: 20px;
        }
        .cta-button {
          display: inline-block;
          padding: 10px 20px;
          background: #007bff;
          color: #fff;
          text-decoration: none;
          border-radius: 5px;
        }
        .case-study img {
          max-width: 100%;
          height: auto;
        }
        .service-list {
          margin-top: 20px;
        }
        .service-list h4 {
          font-size: 24px;
          margin-bottom: 10px;
        }
        .service-list ul {
          list-style: none;
          padding: 0;
        }
        .service-list li {
          padding: 10px 0;
          font-size: 16px;
          display: none;
        }
        .service-list li.active {
          display: block;
        }
      </style>
      <div class="selector-wrapper">
        <h3>Our Services</h3>
        <p>See how we combined our services to solve our clients’ unique project needs.</p>
        <div class="custom-select">
          <button class="custom-select__option custom-select__option--value" id="current-selection" data-value="dispensary">
            A nationwide cannabis dispensary
          </button>
          <div class="custom-select__dropdown" id="dropdown">
            <button class="custom-select__option" data-value="beer">A mission-driven beer brand</button>
            <button class="custom-select__option" data-value="dispensary">A nationwide cannabis dispensary</button>
            <button class="custom-select__option" data-value="resort">An all-inclusive resort</button>
          </div>
        </div>
        <div class="case-study">
          <a id="case-study-link" class="cta-button" href="https://www.brandbureau.com/project/rise" target="_blank">SEE THE rise CASE STUDY</a>
          <img id="case-study-image" src="https://www.brandbureau.com/wp-content/themes/brandbureau/assets/image/dispensary.jpg" alt="">
        </div>
        <div class="service-list">
          <h4>Service List</h4>
          <ul id="service-repeater">
            ${servicesData.map(item => `
              <li data-id="${item._id}" class="${item.categories.join(' ')}">${item.service}</li>
            `).join('')}
          </ul>
        </div>
      </div>
    `;
  }

  setupEventListeners() {
    const dropdownBtn = this.shadowRoot.getElementById('current-selection');
    const dropdown = this.shadowRoot.getElementById('dropdown');

    dropdownBtn.addEventListener('click', () => {
      dropdown.classList.toggle('show');
    });

    dropdown.querySelectorAll('.custom-select__option').forEach(option => {
      option.addEventListener('click', () => {
        const value = option.getAttribute('data-value');
        this.currentSelection = value;
        dropdownBtn.textContent = option.textContent;
        dropdownBtn.setAttribute('data-value', value);
        dropdown.classList.remove('show');
        this.updateCaseStudy(value);
        this.updateRepeater(value);
      });
    });

    window.addEventListener('click', (e) => {
      if (!e.target.closest('.custom-select')) {
        dropdown.classList.remove('show');
      }
    });
  }

  updateCaseStudy(value) {
    const caseStudyLink = this.shadowRoot.getElementById('case-study-link');
    const caseStudyImage = this.shadowRoot.getElementById('case-study-image');
    if (projects[value]) {
      caseStudyLink.textContent = projects[value].text;
      caseStudyLink.href = projects[value].href;
      caseStudyImage.src = projects[value].img;
    }
  }

  updateRepeater(value) {
    const listItems = this.shadowRoot.querySelectorAll('.service-list li');
    listItems.forEach(li => {
      const hasClass = li.classList.contains(value);
      li.classList.toggle('active', hasClass);
    });
  }
}

customElements.define('services-selector', ServicesSelector);
