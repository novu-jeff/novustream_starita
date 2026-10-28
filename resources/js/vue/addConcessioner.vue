<template>
  <form ref="myForm"  @submit.prevent="saveConcessioner">
      <div class="row">
          <div class="col-md-12 mb-3">
              <div class="card shadow border-0">
                  <div class="card-header border-0 bg-primary bg-opacity-25">
                      <div class="text-uppercase fw-bold">Personal Information</div>
                  </div>
                  <div class="card-body">
                    <div class="row border-bottom">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Full Name <small class="text-danger"> ( required )</small></label>
                            <input type="text"
                                  class="form-control"
                                  id="name"
                                  v-model="concessioner.name"
                                  :class="{ 'is-invalid': errors && errors.name }"
                                  >
                            <small v-if="errors.name" class="text-danger px-1">{{ errors.name[0] }}</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="contact_no" class="form-label">Contact No <small v-if="!concessioner.id" class="text-danger"> ( required )</small><small v-else class="text-muted"> ( optional )</small></label>
                            <input type="text"
                                class="form-control"
                                id="contact_no"
                                v-model="concessioner.contact_no"
                                :class="{ 'is-invalid': errors && errors.contact_no }"
                                maxlength="11"
                                @keypress="allowOnlyNumbers"
                            />
                            <small v-if="errors.contact_no" class="text-danger px-1">{{ errors.contact_no[0] }}</small>
                        </div>
                    </div>
                    <div class="text-uppercase fw-bold py-4">Login Information</div>
                    <div class="row mt-3">
                        <div class="col-md-4 mb-3">
                            <label for="email" class="form-label">Email <small v-if="!concessioner.id" class="text-danger"> ( required )</small><small v-else class="text-muted"> ( optional )</small></label>
                            <input type="text"
                                    class="form-control" id="email"
                                    v-model="concessioner.email"
                                    :class="{ 'is-invalid': errors && errors.email }"
                                    >
                            <small v-if="errors.email" class="text-danger px-1">{{ errors.email[0] }}</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="password" class="form-label">
                                Password
                                <small v-if="!concessioner.id" class="text-danger"> ( required )</small>
                                <small v-else class="text-muted"> ( leave blank to keep current password )</small>
                            </label>
                            <div class="input-group">
                                <input :type="showPassword ? 'text' : 'password'"
                                        class="form-control" id="password"
                                        v-model="concessioner.password"
                                        :class="{ 'is-invalid': errors && errors.password }"
                                        autocomplete="new-password"
                                        >
                                <button type="button" class="btn btn-outline-secondary" @click="showPassword = !showPassword">
                                    <i :class="showPassword ? 'bx bx-hide' : 'bx bx-show'"></i>
                                </button>
                            </div>
                            <small v-if="errors.password" class="text-danger px-1">{{ errors.password[0] }}</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="password_confirmation" class="form-label">
                                Confirm Password
                                <small v-if="!concessioner.id" class="text-danger"> ( required )</small>
                                <small v-else class="text-muted"> ( only needed when changing password )</small>
                            </label>
                            <div class="input-group">
                                <input :type="showPasswordConfirm ? 'text' : 'password'"
                                        class="form-control" id="password_confirmation"
                                        v-model="concessioner.password_confirmation"
                                        :class="{ 'is-invalid': errors && errors.password_confirmation }"
                                        autocomplete="new-password"
                                        >
                                <button type="button" class="btn btn-outline-secondary" @click="showPasswordConfirm = !showPasswordConfirm">
                                    <i :class="showPasswordConfirm ? 'bx bx-hide' : 'bx bx-show'"></i>
                                </button>
                            </div>
                            <small v-if="errors.password_confirmation" class="text-danger px-1">{{ errors.password_confirmation[0] }}</small>
                        </div>
                    </div>
                </div>
              </div>
          </div>
      </div>
      <div class="card mt-3">
        <div class="card-header border-0 bg-primary bg-opacity-25 pb-3">
            <div class="text-uppercase fw-bold">Account Informations</div>
        </div>
        <div v-if="registrantId" class="p-3 border-bottom">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="connection_type" class="form-label">
                Connection Type <small class="text-danger">( required )</small>
              </label>
              <select
                id="connection_type"
                class="form-select"
                v-model="concessioner.connection_type"
                :class="{ 'is-invalid': errors && errors.connection_type }"
              >
                <option value="on_line">On-line</option>
                <option value="traverse">Traverse</option>
              </select>
              <small v-if="errors.connection_type" class="text-danger px-1">{{ errors.connection_type[0] }}</small>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Application Fee</label>
              <input class="form-control" value="PHP 4,000.00" disabled>
            </div>
          </div>
          <div v-if="concessioner.connection_type === 'traverse'" class="alert alert-warning mb-0">
            Traverse applications require a Boring/Cutting Permit before final approval.
          </div>
        </div>
        <div class="accordion accordion-flush border-5" v-for="(account, index) in concessioner.accounts" :key="index" id="accordionAccounts">
          <div class="accordion-item border-2 shadow">
            <h2 class="accordion-header d-flex align-items-center gap-3" :id="'flush-account-' + (index + 1)">
              <button
                class="accordion-button d-block text-uppercase fw-bold text-muted"
                :class="{ collapsed: index !== maxIndex }"
                type="button"
                data-bs-toggle="collapse"
                :data-bs-target="'#flush-account-collapse-' + (index + 1)"
                :aria-controls="'flush-account-collapse-' + (index + 1)">
                <div class="mb-1">
                  Account No: {{ account.account_no }}
                </div>
                <small class="text-muted">
                  {{ account.address }}
                </small>
              </button>
              <button type="button" v-if="index != 0" @click="removeAccount(index)" class="remove-account btn btn-danger mb-0 me-3"><i class="bx bx-trash"></i></button>
            </h2>
            <div
              :id="'flush-account-collapse-' + (index + 1)"
              class="accordion-collapse collapse"
              :aria-labelledby="'flush-account-' + (index + 1)"
              data-bs-parent="#accordionAccounts">
                <div class="p-3">
                  <div class="row border-bottom mb-4">
                    <div class="col-md-4 mb-3">
                        <label :for="'account_no_' + index" class="form-label">
                          Account No. <small class="text-danger">( required )</small>
                        </label>
                        <input type="text" class="form-control"
                                :id="'account_no_' + index"
                                v-model="account.account_no"
                                @input="updateZone(index)"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.account_no'] }"
                                >
                        <small v-if="errors['accounts.' + index + '.account_no']" class="text-danger px-1">{{ errors['accounts.' + index + '.account_no'][0] }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label :for="'property_type_' + index" class="form-label">
                          Property Type <small class="text-danger">( required )</small>
                        </label>
                        <select class="form-select"
                                :id="'property_type_' + index"
                                v-model="account.property_type"
                                @change="onPropertyTypeChange(index)"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.property_type'] }">
                        <option :value="null" disabled>-- SELECT --</option>
                        <option v-for="type in property_types" :key="type.id" :value="type.id">
                            {{ type.name.toUpperCase() }}
                        </option>
                        </select>
                        <small v-if="errors['accounts.' + index + '.property_type']" class="text-danger px-1">{{ errors['accounts.' + index + '.property_type'][0] }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label :for="'rate_code_' + index" class="form-label">
                          Rate Code <small class="text-danger">( required )</small>
                        </label>
                        <input type="number" class="form-control"
                              :id="'rate_code_' + index"
                              v-model="account.rate_code"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.rate_code'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.rate_code']" class="text-danger px-1">{{ errors['accounts.' + index + '.rate_code'][0] }}</small>
                    </div>
                    <div class="col-md-6 mb-3">
                    <label class="form-label">
                        Senior Citizen Discount <small class="text-danger">( required )</small>
                    </label>

                    <div class="d-flex gap-4 mt-2">
                        <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            :name="'sc_discount_' + index"
                            :id="'sc_yes_' + index"
                            :value="1"
                            v-model="account.has_sc_discount"
                        >
                        <label class="form-check-label" :for="'sc_yes_' + index">
                            Yes
                        </label>
                        </div>

                        <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            :name="'sc_discount_' + index"
                            :id="'sc_no_' + index"
                            :value="0"
                            v-model="account.has_sc_discount"
                        >
                        <label class="form-check-label" :for="'sc_no_' + index">
                            No
                        </label>
                        </div>
                    </div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label :for="'address_' + index" class="form-label">
                          Address <small class="text-danger">( required )</small>
                        </label>
                        <input type="text"
                            class="form-control"
                            :id="'address_' + index"
                            v-model="account.address"
                            :class="{ 'is-invalid': errors && errors['accounts.' + index + '.address'] }" />
                        <small v-if="errors['accounts.' + index + '.address']" class="text-danger px-1">
                        {{ errors['accounts.' + index + '.address'][0] }}
                        </small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="isActive" class="form-label">
                            Status <small class="text-danger">( required )</small>
                        </label>
                        <select
                        class="form-select"
                        :id="'status_' + index"
                        v-model="account.status"
                        :class="{ 'is-invalid': errors && errors['accounts.' + index + '.status'] }"
                        >
                        <option :value="null" disabled>-- SELECT STATUS --</option>
                        <option
                            v-for="status in status_code"
                            :key="status.id"
                            :value="status.code"
                        >
                            {{ status.name }}
                        </option>
                        </select>
                        <small v-if="errors['accounts.' + index + '.status']" class="text-danger px-1">
                        {{ errors['accounts.' + index + '.status'][0] }}
                        </small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label :for="'meter_serial_no_' + index" class="form-label">
                          Meter Serial No <small class="text-danger">( required )</small>
                        </label>
                        <input type="text" class="form-control"
                              :id="'meter_serial_no_' + index"
                              v-model="account.meter_serial_no"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_serial_no'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.meter_serial_no']" class="text-danger px-1">{{ errors['accounts.' + index + '.meter_serial_no'][0] }}</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label :for="'sc_no_' + index" class="form-label">
                          SC No <small class="text-danger">( required )</small>
                        </label>
                        <input type="text" class="form-control"
                              :id="'sc_no_' + index"
                              v-model="account.sc_no"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.sc_no'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.sc_no']" class="text-danger px-1">{{ errors['accounts.' + index + '.sc_no'][0] }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label :for="'date_connected_' + index" class="form-label">
                          Date Connected <small class="text-danger">( required )</small>
                        </label>
                        <input type="date" class="form-control"
                              :id="'date_connected_' + index"
                              v-model="account.date_connected"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.date_connected'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.date_connected']" class="text-danger px-1">{{ errors['accounts.' + index + '.date_connected'][0] }}</small>
                    </div>
                    <div class="col-md-8 mb-3">
                        <label :for="'sequence_no_' + index" class="form-label">
                          Sequence No <small class="text-danger">( required )</small>
                        </label>
                        <input type="text" class="form-control"
                              :id="'sequence_no_' + index"
                              v-model="account.sequence_no"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.sequence_no'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.sequence_no']" class="text-danger px-1">{{ errors['accounts.' + index + '.sequence_no'][0] }}</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label :for="'meter_brand_' + index" class="form-label">
                          Meter Brand
                        </label>
                        <input type="text" class="form-control"
                                :id="'meter_brand_' + index"
                                v-model="account.meter_brand"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_brand'] }"
                                >
                        <small v-if="errors['accounts.' + index + '.meter_brand']" class="text-danger px-1">{{ errors['accounts.' + index + '.meter_brand'][0] }}</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label :for="'meter_type_' + index" class="form-label">
                          Meter Type
                        </label>
                        <input type="text" class="form-control"
                              :id="'meter_type_' + index"
                              v-model="account.meter_type"
                              :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_type'] }"
                              >
                        <small v-if="errors['accounts.' + index + '.meter_type']" class="text-danger px-1">{{ errors['accounts.' + index + '.meter_type'][0] }}</small>
                    </div>
                    <!-- <div class="col-md-7 mb-3">
                        <label :for="'meter_wire_' + index" class="form-label">
                          Meter Wire
                        </label>
                        <input type="text" class="form-control"
                                :id="'meter_wire_' + index"
                                v-model="account.meter_wire"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_type'] }"
                                >
                    </div>
                    <div class="col-md-5 mb-3">
                        <label :for="'meter_form_' + index" class="form-label">
                          Meter Form
                        </label>
                        <input type="text" class="form-control"
                                  :id="'meter_form_' + index"
                                  v-model="account.meter_form"
                                  :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_form'] }"
                                  >
                        <small v-if="errors['accounts.' + index + '.meter_type']" class="text-danger px-1">{{ errors['accounts.' + index + '.meter_form'][0] }}</small>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label :for="'meter_class_' + index" class="form-label">
                          Meter Class
                        </label>
                        <input type="text" class="form-control"
                                :id="'meter_class_' + index"
                                v-model="account.meter_class"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.meter_class'] }"
                                >
                        <small v-if="errors['accounts.' + index + '.meter_class']" class="text-danger px-1">{{ errors['accounts.' + index + '.meter_class'][0] }}</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label :for="'lat_long_' + index" class="form-label">
                          Latitude/Longitude
                        </label>
                        <input type="text" class="form-control"
                                :id="'lat_long_' + index"
                                v-model="account.lat_long"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.lat_long'] }"
                                >
                        <small v-if="errors['accounts.' + index + '.lat_long']" class="text-danger px-1">{{ errors['accounts.' + index + '.lat_long'][0] }}</small>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label :for="'isErcSealed_' + index" class="form-label">
                          ERC Sealed
                        </label>
                        <select class="form-select"
                                :id="'isErcSealed_' + index"
                                v-model="account.isErcSealed"
                                :class="{ 'is-invalid': errors && errors['accounts.' + index + '.isErcSealed'] }"
                                >
                          <option :value="null">-- SELECT --</option>
                          <option :value="1">Yes</option>
                          <option :value="0">No</option>
                        </select>
                        <small v-if="errors['accounts.' + index + '.isErcSealed']" class="text-danger px-1">{{ errors['accounts.' + index + '.isErcSealed'][0] }}</small>
                    </div>
                    <div class="col-md-12 mb-3">
                      <label :for="'inspectionImage_' + index" class="form-label">
                        Upload Inspection Image
                      </label>
                      <input
                        type="file"
                        class="form-control"
                        :id="'inspectionImage_' + index"
                        @change="handleFileUpload($event, index)"
                        :class="{ 'is-invalid': errors && errors['accounts.' + index + '.inspectionImage'] }"
                      />
                      <small v-if="errors['accounts.' + index + '.inspectionImage']" class="text-danger px-1">
                        {{ errors['accounts.' + index + '.inspectionImage'][0] }}
                      </small>
                    </div>
                    <div v-if="account.inspection_image" class="col-md-12 mb-3">
                      <label :for="'inspectedImage' + index" class="form-label">
                        Inspection Image
                      </label>
                      <div class="card shadow mt-2">
                        <div class="card-body">
                          <div v-if="account.inspection_image" class="col-md-12 mb-3">
                            <div class="lightgallery" :id="'lightgallery-' + index">
                              <a :href="getImageSrc(account)">
                                <img
                                  :src="getImageSrc(account)"
                                  alt="Inspection Preview"
                                  class="w-100 mt-2 image-inspected"
                                />
                              </a>
                            </div>
                          </div>
                        </div>
                      </div>
                  </div> -->
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="d-flex py-3 gap-3 justify-content-end mt-5 pb-5">
        <button type="button" class="btn btn-secondary px-5 py-3 text-uppercase" :disabled="loading" @click="addAccount">Add Account</button>
        <button type="submit" class="btn btn-primary px-5 py-3 text-uppercase d-flex align-items-center gap-2" :disabled="loading">
          Submit
          <div class="spinner-border spinner-border-sm" role="status" v-if="loading">
            <span class="visually-hidden">Loading...</span>
          </div>
        </button>
      </div>

      <div v-if="activationReviewOpen" class="activation-review-backdrop" role="presentation">
        <section class="activation-review-modal" role="dialog" aria-modal="true" aria-labelledby="activation-review-title">
          <header class="activation-review-header">
            <div class="activation-review-avatar"><i class="bx bx-user"></i></div>
            <div class="flex-grow-1">
              <h2 id="activation-review-title">Activate Account</h2>
              <p>Please review the account details before activating.</p>
            </div>
            <button type="button" class="btn-close" aria-label="Close" @click="cancelActivation"></button>
          </header>

          <div v-if="activationReviewLoading" class="activation-review-loading">
            <span class="spinner-border spinner-border-sm" role="status"></span> Loading account details...
          </div>
          <div v-else-if="activationReviewError" class="alert alert-danger m-3">{{ activationReviewError }}</div>
          <div v-else-if="activationReview" class="activation-review-content">
            <section class="activation-review-section">
              <h3><i class="bx bx-id-card"></i> Concessionaire Details</h3>
              <div class="activation-review-grid">
                <div><span>Account No.</span><strong>{{ activationReview.account.account_no }}</strong></div>
                <div><span>Property Type</span><strong>{{ activationReview.account.property_type || 'N/A' }}</strong></div>
                <div><span>Concessionaire Name</span><strong>{{ activationReview.account.name || 'N/A' }}</strong></div>
                <div><span>Rate Code</span><strong>{{ activationReview.account.rate_code || 'N/A' }}</strong></div>
                <div><span>Address</span><strong>{{ activationReview.account.address || 'N/A' }}</strong></div>
                <div><span>Contact No.</span><strong>{{ activationReview.account.contact_no || 'N/A' }}</strong></div>
              </div>
            </section>

            <section class="activation-review-section reading-section">
              <h3><i class="bx bx-tachometer"></i> Last Meter Reading</h3>
              <div v-if="activationReview.reading" class="activation-review-reading-grid">
                <div class="activation-reading-metric">
                  <i class="bx bx-calendar" aria-hidden="true"></i>
                  <span><small>Reading Date</small><strong>{{ formatReviewDate(activationReview.reading.date) }}</strong></span>
                </div>
                <div class="activation-reading-metric">
                  <i class="bx bx-book-reader" aria-hidden="true"></i>
                  <span><small>Previous Reading</small><strong>{{ formatReviewNumber(activationReview.reading.previous_reading) }}</strong></span>
                </div>
                <div class="activation-reading-metric">
                  <i class="bx bx-tachometer" aria-hidden="true"></i>
                  <span><small>Present Reading</small><strong>{{ formatReviewNumber(activationReview.reading.present_reading) }}</strong></span>
                </div>
                <div class="activation-reading-metric">
                  <i class="bx bx-water" aria-hidden="true"></i>
                  <span><small>Consumption</small><strong>{{ formatReviewNumber(activationReview.reading.consumption) }}</strong></span>
                </div>
              </div>
              <p v-else class="activation-review-empty mb-0">No meter reading has been recorded for this account.</p>
              <div v-if="activationReview.reading" class="activation-reading-edit">
                <label class="form-check d-flex align-items-center gap-2 mb-2">
                  <input v-model="activationReview.change_last_reading" class="form-check-input mt-0" type="checkbox">
                  <span>Change Last Reading <small>(if the current reading is incorrect)</small></span>
                </label>
                <div class="activation-reading-edit-fields">
                  <label for="activation-present-reading">Enter corrected present reading</label>
                  <input
                    id="activation-present-reading"
                    v-model.number="activationReview.corrected_present_reading"
                    class="form-control form-control-sm"
                    type="number"
                    :min="activationReview.reading.previous_reading || 0"
                    step="1"
                    :disabled="!activationReview.change_last_reading"
                  >
                  <p>This will be used as the current reading for the next meter reading.</p>
                </div>
              </div>
            </section>

            <section class="activation-review-section" :class="hasUnpaidBills ? 'balance-warning' : 'balance-clear'">
              <h3>
                <i :class="hasUnpaidBills ? 'bx bx-error-circle' : 'bx bx-check-circle'"></i>
                {{ hasUnpaidBills ? 'Outstanding Balance' : 'No Outstanding Balance' }}
              </h3>
              <div v-if="activationReview.unpaid_bills.length" class="activation-balance-fields">
                <fieldset class="activation-unpaid-choice">
                  <legend>Has Unpaid Bills</legend>
                  <label><input v-model="activationReview.has_unpaid_bills" type="radio" :value="true"> Yes</label>
                  <label><input v-model="activationReview.has_unpaid_bills" type="radio" :value="false"> No</label>
                </fieldset>
                <div v-if="activationReview.has_unpaid_bills" class="activation-balance-inputs">
                  <label>
                    <span>Billing Period (Arrears)</span>
                    <select v-model.number="activationReview.selected_bill_id" class="form-select form-select-sm" @change="selectActivationBill">
                      <option v-for="bill in activationReview.unpaid_bills" :key="bill.id" :value="bill.id">
                        {{ formatReviewDate(bill.period_from) }} - {{ formatReviewDate(bill.period_to) }} · {{ bill.reference_no }} · {{ formatReviewCurrency(bill.balance) }}
                      </option>
                    </select>
                  </label>
                  <label>
                    <span>Total Arrears</span>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">PHP</span>
                      <input v-model.number="activationReview.total_arrears" class="form-control" type="number" min="0" step="0.01">
                    </div>
                  </label>
                </div>
                <div v-if="!activationReview.has_unpaid_bills" class="activation-no-unpaid">
                  <i class="bx bx-check-circle"></i>
                  <span>This account has no unpaid bills.</span>
                </div>
              </div>
              <div v-else class="activation-no-unpaid">
                <i class="bx bx-check-circle"></i>
                <span>This account has no unpaid bills.</span>
              </div>
            </section>

            <div v-if="hasUnpaidBills" class="activation-review-warning">
              <i class="bx bx-error"></i>
              <span>This account has unpaid bills. Activating the account will not remove the outstanding balance.</span>
            </div>
          </div>

          <footer class="activation-review-footer">
            <button type="button" class="btn btn-outline-secondary" :disabled="loading" @click="cancelActivation">Cancel</button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="activationReviewLoading || !!activationReviewError || !activationReview || loading"
              @click="confirmActivation"
            >
              {{ activationIndex + 1 < pendingActivations.length ? 'Continue' : 'Confirm Activation' }}
            </button>
          </footer>
        </section>
      </div>
  </form>
</template>

<script>

import { nextTick } from 'vue';
const baseURL = window.location.origin; // http://127.0.0.1:8000


export default {
  mounted() {
    this.lightGallery();
  },
  props: {
    client: {
      type: String,
      required: true
    },
    property_types: {
      type: Array,
      required: true,
    },
    status_code: {
      type: Array,
      required: true,
    },
    data: {
      type: Object,
      required: false,
      default: () => null,
    },
    registrantId: {
      type: [String, Number],
      required: false,
      default: '',
    },
  },
  data() {
    return {
      loading: false,
      disableValidation: true,
      showPassword: false,
      showPasswordConfirm: false,
      concessioner: {
        name: '',
        contact_no: '',
        email: '',
        password: '',
        password_confirmation: '',
        connection_type: 'on_line',
        isActive: 1,
        accounts: [
          {
            account_no: '',
            sc_discount: {
              id_no: ''
            },
            zone: '',
            property_type: '',
            address: '',
            rate_code: '',
            status: '',
            meter_brand: '',
            meter_serial_no: '',
            sc_no: '',
            date_connected: '',
            sequence_no: '',
            meter_type: '',
            meter_wire: '',
            meter_form: '',
            meter_class: '',
            lat_long: '',
            isErcSealed: '',
            inspectionImage: '',
          },
        ],
      },
      errors: [],
      maxIndex: 0,
      originalAccountStatuses: {},
      pendingActivations: [],
      activationIndex: 0,
      activationReview: null,
      activationReviewOpen: false,
      activationReviewLoading: false,
      activationReviewError: '',
      activationConfirmed: false,
      activationDecisions: [],
    };
  },
  computed: {
    hasUnpaidBills() {
      return !!this.activationReview?.has_unpaid_bills;
    },
  },
    created() {
        if (this.data) {
            this.concessioner = {
            ...this.concessioner,
            ...this.data,
            };

            this.concessioner.accounts = this.data.accounts.map(account => {
            const matchedType = this.property_types.find(
                type => type.name.toLowerCase() === account.property_type?.toLowerCase()
            );

            return {
                ...account,
                property_type: matchedType ? matchedType.id : null,
            };
            });

            this.concessioner.connection_type =
              this.data.service_applications?.[0]?.connection_type || 'on_line';

            this.originalAccountStatuses = Object.fromEntries(
              this.concessioner.accounts.map((account) => [String(account.id), String(account.status || '').toUpperCase()])
            );

            console.log(this.concessioner);
        }
    },
  methods: {
    getRateCodeByPropertyTypeId(propertyTypeId) {
        const type = this.property_types.find((t) => t.id === propertyTypeId);
        return type ? type.rate_code : '';
    },
    onPropertyTypeChange(index) {
        const selectedTypeId = this.concessioner.accounts[index].property_type;
        const rateCode = this.getRateCodeByPropertyTypeId(selectedTypeId);
        this.concessioner.accounts[index].rate_code = rateCode;
    },
    getScDiscountIdNo(index) {
      const account = this.concessioner.accounts[index];
      return account && account.sc_discount ? account.sc_discount.id_no : '';
    },
    lightGallery() {
      nextTick(() => {
        document.querySelectorAll('.lightgallery').forEach((gallery) => {
          lightGallery(gallery, {
            plugins: [lgZoom, lgThumbnail],
            speed: 500,
          });
        });
      });
    },
    getZoneFromAccountNo(accountNo) {
        if (!accountNo) return '';
        const zone = accountNo.substring(0, 3);
        return /^\d{3}$/.test(zone) ? zone : '';
    },
    updateZone(index) {
        for (const key in account) {
            const value = account[key];
            formData.append(`accounts[${index}][${key}]`, value ?? '');
        }
    },
    uppercaseAddress(event, index) {
        const input = event.target.value.toUpperCase();
        this.concessioner.accounts[index].address = input;
    },
    getImageSrc(account) {
      if (typeof account.inspection_image === 'string') {
        return '/storage/inspection_images/' + account.account_no + '/' + account.inspection_image;
      }
    },
    handleFileUpload(event, index) {
      const file = event.target.files[0];
      if (file) {
        this.concessioner.accounts[index].inspectionImage = file;
      }
    },
    addAccount() {
      this.concessioner.accounts.push({
        account_no: '',
        property_type: '',
        address: '',
        rate_code: '',
        status: '',
        meter_brand: '',
        meter_serial_no: '',
        sc_no: '',
        date_connected: '',
        sequence_no: '',
        meter_type: '',
        meter_wire: '',
        meter_form: '',
        meter_class: '',
        lat_long: '',
        isErcSealed: true,
        inspectionImage: '',
      });
      this.maxIndex = this.concessioner.accounts.length - 1;
    },
    allowOnlyNumbers(event) {
        const char = String.fromCharCode(event.keyCode);
        if (!/[0-9]/.test(char)) {
            event.preventDefault(); // Block non-numeric keys
        }
    },
    validateForm() {
      if (this.disableValidation) {
        this.errors = {};
        return true;
      }
    let valid = true;
    this.errors = {}; // reset errors
    let errorMessages = [];

    // Contact No validation (required + 11 digits)
    if (!this.concessioner.id && !this.concessioner.contact_no) {
        this.errors.contact_no = ['Contact number is required.'];
        errorMessages.push('Contact number is required.');
        valid = false;
    } else if (this.concessioner.contact_no && !/^\d{11}$/.test(this.concessioner.contact_no)) {
        this.errors.contact_no = ['Contact number must be 11 digits.'];
        errorMessages.push('Contact number must be 11 digits.');
        valid = false;
    }

    // Email is required for new concessionaires and optional when updating.
    if (!this.concessioner.id && !this.concessioner.email) {
        this.errors.email = ['Email is required.'];
        errorMessages.push('Email is required.');
        valid = false;
    } else if (this.concessioner.email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.(com|net|org|edu|gov|ph)$/i;
        if (!emailRegex.test(this.concessioner.email)) {
        this.errors.email = ['Email must be a valid format (example:name@gmail.com).'];
        errorMessages.push('Email must be a valid format.');
        valid = false;
        }
    }

    // Optionally: check full name required
    if (!this.concessioner.name) {
        this.errors.name = ['Full name is required.'];
        errorMessages.push('Full name is required.');
        valid = false;
    }

    // If there are any errors, show an alert
    if (!valid && errorMessages.length) {
        alert('Please fix the following errors:\n' + errorMessages.join('\n'));
    }

    //return valid;
    return true;
    },
    async saveConcessioner() {
        if (this.loading) return;
        this.errors = [];

        if (!this.validateForm()) return;

        this.pendingActivations = this.concessioner.id
          ? this.concessioner.accounts.filter((account) =>
              account.id
              && String(account.status || '').toUpperCase() === 'AB'
              && this.originalAccountStatuses[String(account.id)] !== 'AB'
            )
          : [];

        this.activationConfirmed = false;
        this.activationDecisions = [];
        if (this.pendingActivations.length) {
          this.activationIndex = 0;
          this.activationReviewOpen = true;
          await this.loadActivationReview();
          return;
        }

        this.persistConcessioner();
    },
    async loadActivationReview() {
      this.activationReview = null;
      this.activationReviewError = '';
      this.activationReviewLoading = true;

      try {
        const account = this.pendingActivations[this.activationIndex];
        const response = await axios.get(`${baseURL}/admin/users/concessionaires/${account.id}/activation-review`);
        const review = response.data;
        const unpaidBills = Array.isArray(review.unpaid_bills) ? review.unpaid_bills : [];
        const prepareBill = (bill) => {
          const payableAmount = Number(bill.amount_after_due) > 0
            ? Number(bill.amount_after_due)
            : Number(bill.amount || 0);
          const computedBalance = Math.max(0, payableAmount - Number(bill.credited_amount || 0));

          return {
            ...bill,
            balance: bill.amount_after_due != null || bill.amount != null
              ? computedBalance
              : Number(bill.balance || 0),
          };
        };
        const fallbackOutstandingBill = Number(review.outstanding_balance || 0) > 0
          ? [{
              id: review.outstanding_bill_id ?? null,
              reference_no: review.outstanding_bill_reference || '',
              period_from: review.arrears_period?.from || null,
              period_to: review.arrears_period?.to || null,
              amount: Number(review.outstanding_balance),
              amount_after_due: Number(review.outstanding_balance),
              credited_amount: 0,
              balance: Number(review.outstanding_balance),
            }]
          : [];
        const availableUnpaidBills = unpaidBills.length
          ? unpaidBills.map(prepareBill)
          : fallbackOutstandingBill;
        const latestUnpaidBill = availableUnpaidBills[0] || null;
        this.activationReview = {
          ...review,
          unpaid_bills: availableUnpaidBills,
          has_unpaid_bills: !!latestUnpaidBill,
          selected_bill_id: latestUnpaidBill?.id ?? null,
          total_arrears: latestUnpaidBill?.balance ?? 0,
          change_last_reading: false,
          corrected_present_reading: review.reading?.present_reading ?? null,
        };
      } catch (error) {
        this.activationReviewError = error.response?.data?.message || 'Unable to load account details. Please try again.';
      } finally {
        this.activationReviewLoading = false;
      }
    },
    selectActivationBill() {
      const selectedBill = this.activationReview.unpaid_bills.find(
        (bill) => Number(bill.id) === Number(this.activationReview.selected_bill_id)
      );
      this.activationReview.total_arrears = selectedBill?.balance ?? 0;
    },
    async confirmActivation() {
      this.activationDecisions.push({
        account_id: this.pendingActivations[this.activationIndex].id,
        has_unpaid_bills: !!this.activationReview.has_unpaid_bills,
        bill_id: this.activationReview.selected_bill_id,
        total_arrears: this.activationReview.has_unpaid_bills ? Number(this.activationReview.total_arrears || 0) : 0,
        change_last_reading: !!this.activationReview.change_last_reading,
        reading_id: this.activationReview.reading?.id ?? null,
        corrected_present_reading: this.activationReview.change_last_reading
          ? Number(this.activationReview.corrected_present_reading)
          : null,
      });

      if (this.activationIndex + 1 < this.pendingActivations.length) {
        this.activationIndex += 1;
        await this.loadActivationReview();
        return;
      }

      this.activationConfirmed = true;
      this.activationReviewOpen = false;
      this.persistConcessioner();
    },
    cancelActivation() {
      this.activationReviewOpen = false;
      this.pendingActivations = [];
      this.activationReview = null;
      this.activationReviewError = '';
    },
    formatReviewNumber(value) {
      return value == null ? 'N/A' : Number(value).toLocaleString('en-PH');
    },
    formatReviewCurrency(value) {
      return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0));
    },
    formatReviewDate(value) {
      if (!value) return 'N/A';
      const date = new Date(`${value}T00:00:00`);
      return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' });
    },
    persistConcessioner() {
        this.loading = true;

        // --- continue existing saveConcessioner logic ---
        this.concessioner.accounts.forEach((account, index) => {
            account.zone = account.account_no ? account.account_no.substring(0, 3) : '';
        });

        let method = 'post';
        let endpoint = `${baseURL}/admin/users/concessionaires`;
        let data = { ...this.concessioner };

        if (this.concessioner.id) {
            endpoint = `${baseURL}/admin/users/concessionaires/${this.concessioner.id}`;
            data._method = 'PUT'; // method spoofing
        }

      const formData = new FormData();

      // Append top-level fields
      for (const key in this.concessioner) {
        if (key !== 'accounts') {
          if (
            this.concessioner.id &&
            ['password', 'password_confirmation'].includes(key) &&
            !this.concessioner[key]
          ) {
            continue;
          }

          formData.append(key, this.concessioner[key]);
        }
      }

      if (this.registrantId) {
        formData.append('registrant_id', this.registrantId);
      }

      // Append nested accounts
      this.concessioner.accounts.forEach((account, index) => {
        for (const key in account) {
          const value = account[key];
          if (key === 'inspectionImage' && value instanceof File) {
            formData.append(`accounts[${index}][${key}]`, value);
          } else {
            formData.append(`accounts[${index}][${key}]`, value ?? '');
          }
        }
      });

      if (this.concessioner.id != null) {
        formData.append('_method', 'PUT');
      }
      if (this.activationConfirmed) {
        formData.append('activation_confirmed', '1');
        formData.append('activation_reviews', JSON.stringify(this.activationDecisions));
      }

      axios({
        method: method,
        url: endpoint,
        data: formData,
        headers: {
          'X-CSRF-TOKEN': document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute('content'),
          'Content-Type': 'multipart/form-data',
        },
      })
        .then((response) => {
          if (this.concessioner.id == null) {
            this.resetForm();
          } else {
            this.originalAccountStatuses = Object.fromEntries(
              this.concessioner.accounts.map((account) => [String(account.id), String(account.status || '').toUpperCase()])
            );
          }
          alert(response.data.status, response.data.message);
        })
        .catch((error) => {
          if (error.response && error.response.status === 422) {
            const errors = error.response.data.errors
            this.errors = errors;
            if (errors.accounts) {
              alert('error', errors.accounts[0]);
            }
          } else {
            alert(error.status, error.message);
          }
        })
        .finally(() => {
          this.loading = false;
          this.activationConfirmed = false;
          this.pendingActivations = [];
          this.activationDecisions = [];
        });
    },
    removeAccount(index) {
      this.concessioner.accounts.splice(index, 1);
    },
    resetForm() {
      this.concessioner = {
        name: '',
        senior_citizen_no: '',
        contact_no: '',
        email: '',
        password: '',
        password_confirmation: '',
        accounts: [
          {
            account_no: '',
            property_type: '',
            address: '',
            rate_code: '',
            status: '',
            meter_brand: '',
            meter_serial_no: '',
            sc_no: '',
            date_connected: '',
            sequence_no: '',
            meter_type: '',
            meter_wire: '',
            meter_form: '',
            meter_class: '',
            lat_long: '',
            isErcSealed: '',
            inspectionImage: '',
          },
        ],
      };
    },
  },
};

</script>

<style>
  .activation-review-backdrop {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(15, 33, 54, 0.62);
    backdrop-filter: blur(4px);
  }

  .activation-review-modal {
    width: min(100%, 850px);
    max-height: min(92vh, 900px);
    overflow-y: auto;
    border: 1px solid #d6e5f3;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 24px 70px rgba(8, 31, 57, 0.3);
  }

  .activation-review-header,
  .activation-review-footer {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.25rem;
  }

  .activation-review-header {
    border-bottom: 1px solid #e6edf4;
  }

  .activation-review-avatar {
    display: grid;
    width: 48px;
    aspect-ratio: 1;
    place-items: center;
    border-radius: 50%;
    background: #e7f2ff;
    color: #1672e8;
    font-size: 1.6rem;
  }

  .activation-review-header h2 {
    margin: 0;
    color: #123b70;
    font-size: 1.25rem;
    font-weight: 700;
  }

  .activation-review-header p {
    margin: 0.15rem 0 0;
    color: #60758d;
    font-size: 0.85rem;
  }

  .activation-review-content {
    display: grid;
    gap: 0.8rem;
    padding: 1rem 1.25rem;
  }

  .activation-review-section {
    overflow: hidden;
    border: 1px solid #cfe2f5;
    border-radius: 7px;
    background: #fbfdff;
  }

  .activation-review-section h3 {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    margin: 0;
    padding: 0.65rem 0.8rem;
    background: #eff7ff;
    color: #164b87;
    font-size: 0.95rem;
    font-weight: 700;
  }

  .activation-review-section h3 i {
    font-size: 1.25rem;
  }

  .activation-review-grid,
  .activation-review-reading-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.5rem;
    padding: 0.85rem;
  }

  .activation-review-grid > div,
  .activation-reading-metric > span {
    display: grid;
    gap: 0.15rem;
    min-width: 0;
  }

  .activation-reading-metric {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    min-width: 0;
    padding-right: 0.45rem;
    border-right: 1px solid #d5e7f8;
  }

  .activation-reading-metric:last-child {
    border-right: 0;
  }

  .activation-reading-metric > i {
    flex: 0 0 auto;
    color: #1672e8;
    font-size: 1.25rem;
  }

  .activation-reading-metric small {
    color: #637d99;
    font-size: 0.72rem;
    line-height: 1.2;
  }

  .activation-review-grid span,
  .activation-review-balance span {
    color: #637d99;
    font-size: 0.77rem;
  }

  .activation-review-grid strong,
  .activation-reading-metric strong {
    overflow-wrap: anywhere;
    color: #163f71;
    font-size: 0.88rem;
    font-weight: 600;
  }

  .reading-section {
    border-color: #b9dafa;
  }

  .reading-section h3 {
    background: #eaf5ff;
  }

  .activation-reading-edit {
    margin: 0 0.75rem 0.75rem;
    padding: 0.65rem 0.75rem;
    border: 1px solid #cfe2f5;
    border-radius: 6px;
    background: #f2f8fe;
    color: #164b87;
    font-size: 0.8rem;
  }

  .activation-reading-edit .form-check-input {
    border-color: #1672e8;
  }

  .activation-reading-edit small {
    color: #627d99;
  }

  .activation-reading-edit-fields {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(150px, 0.9fr) minmax(0, 1fr);
    align-items: center;
    gap: 0.65rem;
    padding-left: 1.65rem;
  }

  .activation-reading-edit-fields label,
  .activation-balance-inputs label {
    display: grid;
    gap: 0.3rem;
    color: #607995;
    font-size: 0.75rem;
  }

  .activation-reading-edit-fields p {
    margin: 0;
    color: #607995;
    font-size: 0.75rem;
  }

  .activation-balance-fields {
    padding: 0.8rem;
  }

  .activation-unpaid-choice {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin: 0 0 0.7rem;
    border: 0;
    padding: 0;
  }

  .activation-unpaid-choice legend {
    float: left;
    width: auto;
    margin: 0 0.25rem 0 0;
    color: #637d99;
    font-size: 0.75rem;
  }

  .activation-unpaid-choice label {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    color: #164b87;
    font-size: 0.8rem;
  }

  .activation-unpaid-choice input {
    accent-color: #1672e8;
  }

  .activation-balance-inputs {
    display: grid;
    grid-template-columns: 1fr 0.8fr;
    gap: 0.75rem;
  }

  .activation-no-unpaid {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.7rem;
    border: 1px solid #c4eadc;
    border-radius: 6px;
    background: #effaf5;
    color: #167b58;
    font-size: 0.82rem;
  }

  .activation-no-unpaid i {
    font-size: 1.35rem;
  }

  .activation-review-empty,
  .activation-review-balance {
    padding: 0.85rem;
    color: #526a83;
    font-size: 0.86rem;
  }

  .balance-warning {
    border-color: #ffcbd0;
    background: #fffafb;
  }

  .balance-warning h3 {
    background: #fff0f1;
    color: #bd2436;
  }

  .balance-clear {
    border-color: #c4eadc;
    background: #fbfefc;
  }

  .balance-clear h3 {
    background: #effaf5;
    color: #167b58;
  }

  .activation-review-balance {
    display: grid;
    gap: 0.25rem;
  }

  .activation-review-balance strong {
    color: #bd2436;
    font-size: 1.2rem;
  }

  .activation-review-balance small {
    color: #718198;
  }

  .activation-review-warning {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.75rem 0.85rem;
    border: 1px solid #f5d28d;
    border-radius: 7px;
    background: #fff8e9;
    color: #80540a;
    font-size: 0.82rem;
  }

  .activation-review-warning i {
    flex: 0 0 auto;
    font-size: 1.35rem;
  }

  .activation-review-loading {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.65rem;
    min-height: 180px;
    color: #526a83;
  }

  .activation-review-footer {
    justify-content: flex-end;
    border-top: 1px solid #e6edf4;
  }

  @media (max-width: 575px) {
    .activation-review-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.65rem;
    }

    .activation-review-reading-grid {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 0.65rem 0.45rem;
    }

    .activation-reading-metric:nth-child(2) {
      border-right: 0;
    }

    .activation-reading-metric:nth-child(3),
    .activation-reading-metric:nth-child(4) {
      padding-top: 0.45rem;
      border-top: 1px solid #d5e7f8;
    }

    .activation-review-header,
    .activation-review-footer {
      padding: 0.85rem;
    }

    .activation-review-content {
      padding: 0.75rem;
    }

    .activation-reading-edit-fields,
    .activation-balance-inputs {
      grid-template-columns: 1fr;
      padding-left: 0;
    }

    .activation-unpaid-choice {
      flex-wrap: wrap;
    }
  }

  img.image-inspected {
    width: 100%;
    height: 500px;
    overflow-y: scroll;
    object-fit: cover;
    cursor: pointer;
  }

  .remove-account {
    position: absolute;
    right: 0;
    z-index: 999;
  }

</style>
