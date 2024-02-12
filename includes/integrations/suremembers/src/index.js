import WpfSelect from '@verygoodplugins/wpfselect';
import { useState } from '@wordpress/element';

const { addFilter } = wp.hooks;
const { __ } = wp.i18n;

addFilter( 'suremembers_sidebar_metaboxes_after', 'wpfusion', function ( fxn ) {
    const [ applyTags, setApplyTags ]             = useState( wpf_suremembers.apply_tags );
    const [ linkTags, setLinkTags ]               = useState( wpf_suremembers.tag_link );
    const [ staticApplyTags, setStaticApplyTags ] = useState( wpf_suremembers.raw_apply_tags );
    const [ staticLinkTags, setStaticLinkTags ]   = useState( wpf_suremembers.raw_tag_link );

    const applyTagsString = wpf_suremembers.apply_tags_string;
    const tagLinkString   = wpf_suremembers.tag_link_string;

    const onChange = ( holder, value, single = false ) => {
        let string = '';

        if( single ){
            string = value.value + ',';
        }else{
            value.forEach( ( tag, index ) => {
                string = string + tag.value + ',';
            } );
        }

        return string;
    }

    return(
        <div>
            <div className="bg-white rounded-sm divide-y-[1px] w-full" id="wpf_meta_box_suremembers">
                <div className="px-8 py-5 border-solid  border-bottom-gray-500 font-medium text-[15px]" id="wpf_meta_box_suremembers_title">
                    { __( "WP Fusion", "wp-fusion" ) }
                </div>
                <div className="px-8 py-6 text-sm" id="wpf_meta_box_suremembers_content">
                    <div className="flex flex-col space-y-4">
                        <input type="hidden" name='wpf_meta_box_suremembers_nonce' value={wpf_suremembers.nonce} />
                        <input type="hidden" id='wpf-suremembers-apply-tags' name="wp_fusion[apply_tags]" value={staticApplyTags} />
                        <sc-form-control className="hydrated" id="tag_apply" label={ wpf_admin.strings.applyTags } size="medium">
                            <WpfSelect
                                existingTags={ applyTags }
                                onChange={ ( value ) => {
                                    const holder = document.getElementById( 'wpf-suremembers-apply-tags' );

                                    const processedValue = onChange( holder, value );

                                    setStaticApplyTags( processedValue );
                                    setApplyTags( value )
                                } }
                                elementID = 'wpf-sure-members-tags'
                            />
                        </sc-form-control>
                        <span className="description">
                        { applyTagsString }
                    </span>
                        <div className="flex flex-col space-y-4">
                            <input type="hidden" id='wpf-suremembers-link-tags' name="wp_fusion[tag_link]" value={staticLinkTags} />
                            <sc-form-control className="hydrated" id="tag_link" label={ wpf_admin.strings.linkWithTag } size="medium">
                                <WpfSelect
                                    existingTags={ linkTags }
                                    onChange={ ( value ) => {
                                        const holder = document.getElementById( 'wpf-suremembers-link-tags' );

                                        const processedValue = onChange( holder, value, true );

                                        setStaticLinkTags( processedValue );
                                        setLinkTags( value );
                                    } }
                                    elementID = 'wpf-sure-members-line'
                                    isMulti = {false}
                                />
                            </sc-form-control>
                            <span class="description">
                                { tagLinkString }
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
} );